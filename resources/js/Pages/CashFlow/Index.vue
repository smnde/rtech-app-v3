<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Wallet, TrendingUp, TrendingDown, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppStatCard from '@/Components/UI/AppStatCard.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    flows:      { type: Object, required: true },
    summary:    { type: Object, required: true },
    categories: { type: Object, default: () => ({}) },
    filters:    { type: Object, default: () => ({}) },
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

const rows = computed(() => props.flows.data);

// ─── Filter ──────────────────────────────────────────────────────────────────

const todayDate = new Date().toISOString().slice(0, 10);

const filterForm = reactive({
    period: { from: props.filters.from ?? todayDate, to: props.filters.to ?? todayDate },
    type: props.filters.type ?? '',
});

const typeOptions = [
    { id: '', label: 'Semua tipe' },
    { id: 'in', label: 'Masuk' },
    { id: 'out', label: 'Keluar' },
];

const hasActiveFilter = computed(() =>
    filterForm.type !== ''
    || filterForm.period.from !== todayDate
    || filterForm.period.to !== todayDate
);

const applyFilter = () => {
    router.get(route('cashflow.index'), {
        from: filterForm.period.from || undefined,
        to:   filterForm.period.to   || undefined,
        type: filterForm.type        || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilter = () => {
    filterForm.period = { from: todayDate, to: todayDate };
    filterForm.type = '';
    applyFilter();
};

watch(() => filterForm.type, applyFilter);
watch(() => filterForm.period, applyFilter, { deep: true });

// ─── Form ─────────────────────────────────────────────────────────────────────

const showModal = ref(false);
const selected = ref(null);
const saving = ref(false);
const form = reactive({
    type: 'in',
    category: '',
    description: '',
    amount: '',
    flow_date: todayDate,
});
const errors = ref({});

const categoryOptions = computed(() => [
    { id: '', label: 'Pilih kategori' },
    ...Object.entries(props.categories).map(([id, label]) => ({ id, label })),
]);

const openCreate = () => {
    selected.value = null;
    Object.assign(form, { type: 'in', category: '', description: '', amount: '', flow_date: todayDate });
    errors.value = {};
    showModal.value = true;
};

const openEdit = (item) => {
    selected.value = item;
    Object.assign(form, {
        type: item.type,
        category: item.category,
        description: item.description,
        amount: item.amount,
        flow_date: item.flow_date,
    });
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selected.value = null;
};

const validate = () => {
    const e = {};
    if (!form.type) e.type = 'Tipe wajib dipilih.';
    if (!form.category) e.category = 'Kategori wajib dipilih.';
    if (!form.description.trim()) e.description = 'Keterangan wajib diisi.';
    if (!form.amount || Number(form.amount) < 1) e.amount = 'Nominal wajib diisi.';
    if (!form.flow_date) e.flow_date = 'Tanggal wajib diisi.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const submit = () => {
    if (!validate()) return;
    saving.value = true;
    const payload = {
        type:        form.type,
        category:    form.category,
        description: form.description,
        amount:      Number(form.amount),
        flow_date:   form.flow_date,
    };
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (serverErrors) => toast.error(Object.values(serverErrors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    };

    if (selected.value) {
        router.put(route('cashflow.update', selected.value.id), payload, options);
    } else {
        router.post(route('cashflow.store'), payload, options);
    }
};

const handleDelete = async (item) => {
    const ok = await confirm.confirm({
        title: 'Hapus mutasi kas?',
        message: `"${item.description}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('cashflow.destroy', item.id), { preserveScroll: true });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const typeColor = { in: 'green', out: 'red' };
const typeLabel = { in: 'Masuk', out: 'Keluar' };

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';
const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError
        ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
        : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];
</script>

<template>
    <Head title="Kas" />

    <AppLayout title="Kas">
        <template v-if="can('add cashflow')" #fab>
            <AppButton
                variant="primary" size="md"
                class="!h-14 !w-14 !rounded-full !p-0 shadow-lg"
                title="Tambah Mutasi Kas"
                @click="openCreate"
            >
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <div class="flex h-full flex-col">

            <AppPageHeader title="Kas" description="Catatan mutasi kas masuk dan keluar." class="shrink-0">
                <template #actions>
                    <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipe</label>
                                <AppDropdown v-model="filterForm.type" :options="typeOptions" placeholder="Semua tipe" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode</label>
                                <AppDateRangePicker v-model="filterForm.period" />
                            </div>
                        </div>
                    </AppFilterModal>
                    <AppButton
                        v-if="can('add cashflow')"
                        variant="primary" size="md"
                        class="hidden w-full sm:flex sm:w-auto"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        Tambah Mutasi
                    </AppButton>
                </template>
            </AppPageHeader>

            <!-- Summary cards -->
            <div class="mb-5 shrink-0 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <AppStatCard
                    label="Total Saldo"
                    :value="formatRupiah(summary.balance)"
                    :icon="Wallet"
                    icon-color="purple"
                />
                <AppStatCard
                    :label="`Kas Masuk (periode)`"
                    :value="formatRupiah(summary.period_in)"
                    :icon="TrendingUp"
                    icon-color="green"
                />
                <AppStatCard
                    :label="`Kas Keluar (periode)`"
                    :value="formatRupiah(summary.period_out)"
                    :icon="TrendingDown"
                    icon-color="red"
                />
            </div>

            <!-- Desktop filter bar -->
            <div class="hidden shrink-0 mb-4 sm:flex flex-wrap items-center gap-3">
                <div class="w-44">
                    <AppDropdown v-model="filterForm.type" :options="typeOptions" placeholder="Semua tipe" />
                </div>
                <div class="w-72">
                    <AppDateRangePicker v-model="filterForm.period" />
                </div>
                <AppButton v-if="hasActiveFilter" variant="ghost" size="sm" @click="resetFilter">
                    <X class="h-4 w-4" />
                    Reset filter
                </AppButton>
            </div>

            <!-- DESKTOP: tabel -->
            <AppTable :is-empty="rows.length === 0" empty-message="Tidak ada mutasi kas." class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800/60">
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipe</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kategori</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nominal</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dicatat oleh</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="item in rows" :key="item.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                    >
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ item.flow_date }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="typeColor[item.type] ?? 'gray'" size="sm" dot>
                                {{ typeLabel[item.type] ?? item.type }}
                            </AppBadge>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ item.category }}</td>
                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ item.description }}</td>
                        <td class="px-5 py-3 text-sm font-semibold whitespace-nowrap"
                            :class="item.type === 'in' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ item.type === 'in' ? '+' : '-' }}{{ formatRupiah(item.amount) }}
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ item.user_name ?? '-' }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <AppButton
                                    v-if="can('edit cashflow')"
                                    variant="ghost" size="sm" icon-only title="Edit"
                                    @click="openEdit(item)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton
                                    v-if="can('delete cashflow')"
                                    variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(item)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #pagination>
                    <AppPagination :paginator="flows" />
                </template>
            </AppTable>

            <!-- MOBILE: card list -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Wallet class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada mutasi kas.</p>
                </div>

                <div
                    v-for="item in rows" :key="item.id"
                    class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.description }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">{{ item.flow_date }} · {{ item.category }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <AppBadge :color="typeColor[item.type] ?? 'gray'" size="sm" dot>
                                {{ typeLabel[item.type] ?? item.type }}
                            </AppBadge>
                            <AppButton
                                v-if="can('edit cashflow')"
                                variant="ghost" size="sm" icon-only
                                @click="openEdit(item)"
                            >
                                <Pencil class="h-4 w-4" />
                            </AppButton>
                            <AppButton
                                v-if="can('delete cashflow')"
                                variant="ghost" size="sm" icon-only
                                class="hover:!text-red-500 dark:hover:!text-red-400"
                                @click="handleDelete(item)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </AppButton>
                        </div>
                    </div>
                    <p class="mt-2 text-base font-bold"
                        :class="item.type === 'in' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        {{ item.type === 'in' ? '+' : '-' }}{{ formatRupiah(item.amount) }}
                    </p>
                </div>

                <div v-if="flows.total > 0" class="mt-4">
                    <AppPagination :paginator="flows" />
                </div>
            </div>
        </div>

        <!-- Modal tambah / edit -->
        <AppModal
            :show="showModal"
            :title="selected ? 'Edit Mutasi Kas' : 'Tambah Mutasi Kas'"
            size="md"
            @close="closeModal"
        >
            <form class="space-y-4" novalidate @submit.prevent="submit">
                <!-- Tipe -->
                <div>
                    <label :class="labelClass">Tipe <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <label
                            v-for="opt in [{ value: 'in', label: 'Kas Masuk' }, { value: 'out', label: 'Kas Keluar' }]"
                            :key="opt.value"
                            :class="[
                                'flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                form.type === opt.value
                                    ? opt.value === 'in'
                                        ? 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 dark:border-green-500'
                                        : 'border-red-500 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 dark:border-red-500'
                                    : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800',
                            ]"
                        >
                            <input v-model="form.type" type="radio" :value="opt.value" class="sr-only" />
                            <component :is="opt.value === 'in' ? TrendingUp : TrendingDown" class="h-4 w-4" />
                            {{ opt.label }}
                        </label>
                    </div>
                    <p v-if="errors.type" class="mt-1.5 text-xs text-red-500">{{ errors.type }}</p>
                </div>

                <!-- Kategori -->
                <div>
                    <label :class="labelClass">Kategori <span class="text-red-500">*</span></label>
                    <AppDropdown v-model="form.category" :options="categoryOptions" placeholder="Pilih kategori" />
                    <p v-if="errors.category" class="mt-1.5 text-xs text-red-500">{{ errors.category }}</p>
                </div>

                <!-- Keterangan -->
                <div>
                    <label :class="labelClass">Keterangan <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.description"
                        type="text"
                        placeholder="Contoh: Setor modal awal bulan Oktober"
                        :class="inputClass(!!errors.description)"
                    />
                    <p v-if="errors.description" class="mt-1.5 text-xs text-red-500">{{ errors.description }}</p>
                </div>

                <!-- Nominal -->
                <div>
                    <label :class="labelClass">Nominal <span class="text-red-500">*</span></label>
                    <AppCurrencyInput v-model="form.amount" :error="!!errors.amount" placeholder="0" />
                    <p v-if="errors.amount" class="mt-1.5 text-xs text-red-500">{{ errors.amount }}</p>
                </div>

                <!-- Tanggal -->
                <div>
                    <label :class="labelClass">Tanggal <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.flow_date"
                        type="date"
                        :class="inputClass(!!errors.flow_date)"
                    />
                    <p v-if="errors.flow_date" class="mt-1.5 text-xs text-red-500">{{ errors.flow_date }}</p>
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
</template>
