<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Receipt, Eye } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import AppActionDrawer from '@/Components/UI/AppActionDrawer.vue';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    expenses: { type: Object, required: true },
    users:    { type: Array, default: () => [] },
    filters:  { type: Object, default: () => ({}) },
});

const { can } = usePermission();
const toast = useToast();
const confirm = useConfirm();
const page = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const rows = computed(() => props.expenses.data);

const filterForm = reactive({
    period: { from: props.filters.from ?? '', to: props.filters.to ?? '' },
    user_id: props.filters.user_id ?? '',
});

const userOptions = computed(() => [
    { id: '', label: 'Semua user' },
    ...props.users.map((user) => ({ id: String(user.id), label: user.name })),
]);

const hasActiveFilter = computed(() => filterForm.user_id !== '' || filterForm.period.from !== '' || filterForm.period.to !== '');

const resetFilter = () => {
    filterForm.period = { from: '', to: '' };
    filterForm.user_id = '';
    applyFilter();
};

const applyFilter = () => {
    router.get(route('expenses.index'), {
        from: filterForm.period.from || undefined,
        to: filterForm.period.to || undefined,
        user_id: filterForm.user_id || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const showModal = ref(false);
const selected = ref(null);
const saving = ref(false);
const form = reactive({ category: '', description: '', amount: '' });
const errors = ref({});

const openCreate = () => {
    selected.value = null;
    Object.assign(form, { category: '', description: '', amount: '' });
    errors.value = {};
    showModal.value = true;
};

const openEdit = (item) => {
    selected.value = item;
    Object.assign(form, { category: item.category, description: item.description, amount: item.amount });
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selected.value = null;
};

const validate = () => {
    const e = {};
    if (!form.category.trim()) e.category = 'Kategori wajib diisi.';
    if (!form.description.trim()) e.description = 'Keterangan wajib diisi.';
    if (!form.amount || Number(form.amount) < 1) e.amount = 'Nominal wajib diisi.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const submit = () => {
    if (!validate()) return;
    saving.value = true;
    const payload = {
        category: form.category,
        description: form.description,
        amount: Number(form.amount),
    };
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (serverErrors) => toast.error(Object.values(serverErrors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    };

    if (selected.value) {
        router.put(route('expenses.update', selected.value.id), payload, options);
    } else {
        router.post(route('expenses.store'), payload, options);
    }
};

const handleDelete = async (item) => {
    const ok = await confirm.confirm({
        title: 'Hapus pengeluaran?',
        message: `"${item.description}" akan dihapus.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('expenses.destroy', item.id), { preserveScroll: true });
};

const formatRupiah = (value) => `Rp ${Number(value).toLocaleString('id-ID')}`;

const formatDateTime = (value) => (value
    ? new Date(String(value).replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

// Mobile action drawer
const showActionDrawer = ref(false);
const actionDrawerItem = ref(null);

const openActionDrawer = (item) => {
    actionDrawerItem.value = item;
    showActionDrawer.value = true;
};

const closeActionDrawer = () => {
    showActionDrawer.value = false;
    actionDrawerItem.value = null;
};

const getDrawerActions = (item) => {
    const actions = [];

    // Lihat Detail
    actions.push({
        label: 'Lihat Detail',
        icon: Eye,
        onClick: () => {
            closeActionDrawer();
            router.visit(route('expenses.show', item.id));
        },
    });

    // Divider before edit/delete
    if (can('edit expense') || can('delete expense')) {
        actions.push({ divider: true });
    }

    // Edit
    if (can('edit expense')) {
        actions.push({
            label: 'Edit',
            icon: Pencil,
            onClick: () => {
                closeActionDrawer();
                openEdit(item);
            },
        });
    }

    // Divider before delete
    if (can('delete expense')) {
        actions.push({ divider: true });

        actions.push({
            label: 'Hapus',
            icon: Trash2,
            class: 'text-red-600 dark:text-red-400',
            onClick: () => {
                closeActionDrawer();
                handleDelete(item);
            },
        });
    }

    return actions;
};

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';
const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError ? 'border-red-400 dark:border-red-500 focus:ring-red-400' : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];
</script>

<template>
    <Head title="Pengeluaran" />

    <AppLayout title="Pengeluaran">
        <template v-if="can('add expense')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Pengeluaran" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <div class="flex h-full flex-col">
        <AppPageHeader title="Pengeluaran" description="Catatan biaya operasional toko." class="shrink-0">
            <template #actions>
                <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode</label>
                        <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Dicatat oleh</label>
                        <AppDropdown v-model="filterForm.user_id" :options="userOptions" @update:model-value="applyFilter" />
                    </div>
                </AppFilterModal>
                <AppButton v-if="can('add expense')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Tambah Pengeluaran
                </AppButton>
            </template>
        </AppPageHeader>

        <AppCard class="mb-5 hidden shrink-0 sm:block">
            <div class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-80">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Periode</label>
                    <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                </div>
                <div class="w-56">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dicatat oleh</label>
                    <AppDropdown v-model="filterForm.user_id" :options="userOptions" @update:model-value="applyFilter" />
                </div>
            </div>
        </AppCard>

        <AppTable :is-empty="rows.length === 0" empty-message="Belum ada pengeluaran." class="flex-1 min-h-0">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/60">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kategori</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nominal</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dicatat oleh</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="px-5 py-3 text-sm text-gray-500 whitespace-nowrap">{{ formatDateTime(item.date) }}</td>
                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ item.category }}</td>
                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ item.description }}</td>
                    <td class="px-5 py-3 text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ formatRupiah(item.amount) }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">{{ item.user_name ?? '-' }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-1">
                            <AppButton v-if="can('edit expense')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(item)">
                                <Pencil class="h-4 w-4" />
                            </AppButton>
                            <AppButton v-if="can('delete expense')" variant="ghost" size="sm" icon-only title="Hapus"
                                class="hover:!text-red-500 dark:hover:!text-red-400" @click="handleDelete(item)">
                                <Trash2 class="h-4 w-4" />
                            </AppButton>
                        </div>
                    </td>
                </tr>
            </tbody>
            <template #pagination>
                <AppPagination :paginator="expenses" />
            </template>

        </AppTable>
        </div>

        <!-- MOBILE VIEW: Card list -->
        <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
            <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                    <Receipt class="h-6 w-6 text-gray-400" />
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada pengeluaran ditemukan.</p>
            </div>

            <div
                v-for="item in rows"
                :key="item.id"
                class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm cursor-pointer active:bg-gray-50 dark:active:bg-gray-800/50"
                @click="openActionDrawer(item)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ item.category }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ formatDateTime(item.date) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatRupiah(item.amount) }}</p>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ item.description }}</p>
                    <p class="text-xs text-gray-500">Dicatat oleh: {{ item.user_name ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div v-if="expenses.total > 0" class="mt-4 sm:hidden">
            <AppPagination :paginator="expenses" />
        </div>

        <AppModal :show="showModal" :title="selected ? 'Edit Pengeluaran' : 'Tambah Pengeluaran'" size="md" @close="closeModal">
            <form class="space-y-4" novalidate @submit.prevent="submit">
                <div>
                    <label :class="labelClass">Kategori <span class="text-red-500">*</span></label>
                    <input v-model="form.category" type="text" placeholder="Contoh: Listrik, Sewa, ATK" :class="inputClass(!!errors.category)" />
                    <p v-if="errors.category" class="mt-1.5 text-xs text-red-500">{{ errors.category }}</p>
                </div>
                <div>
                    <label :class="labelClass">Keterangan <span class="text-red-500">*</span></label>
                    <input v-model="form.description" type="text" placeholder="Contoh: Bayar listrik Oktober" :class="inputClass(!!errors.description)" />
                    <p v-if="errors.description" class="mt-1.5 text-xs text-red-500">{{ errors.description }}</p>
                </div>
                <div>
                    <label :class="labelClass">Nominal <span class="text-red-500">*</span></label>
                    <AppCurrencyInput v-model="form.amount" :error="!!errors.amount" placeholder="0" />
                    <p v-if="errors.amount" class="mt-1.5 text-xs text-red-500">{{ errors.amount }}</p>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="closeModal">Batal</AppButton>
                <AppButton variant="primary" size="sm" :loading="saving" @click="submit">
                    {{ selected ? 'Simpan Perubahan' : 'Simpan' }}
                </AppButton>
            </template>
        </AppModal>
    </AppLayout>

    <!-- Mobile Action Drawer -->
    <AppActionDrawer
        :show="showActionDrawer"
        :title="actionDrawerItem ? actionDrawerItem.category : 'Aksi'"
        :item="actionDrawerItem"
        :actions="actionDrawerItem ? getDrawerActions(actionDrawerItem) : []"
        @close="closeActionDrawer"
    />
</template>
