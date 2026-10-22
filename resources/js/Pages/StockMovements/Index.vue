<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, X, Pencil, Trash2, Eye, Layers } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppActionDrawer from '@/Components/UI/AppActionDrawer.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import AppNumberInput from '@/Components/UI/AppNumberInput.vue';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    movements: { type: Object, required: true },
    items:     { type: Array, default: () => [] },
    filters:   { type: Object, default: () => ({}) },
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

const rows = computed(() => props.movements.data);

const TYPE_OPTIONS = [
    { id: 'all', label: 'Semua tipe' },
    { id: 'in', label: 'Masuk' },
    { id: 'out', label: 'Keluar' },
    { id: 'adjustment', label: 'Penyesuaian' },
];
const TYPE_FORM_OPTIONS = TYPE_OPTIONS.slice(1);
const TYPE_COLOR = { in: 'green', out: 'red', adjustment: 'yellow' };
const TYPE_LABEL = { in: 'Masuk', out: 'Keluar', adjustment: 'Penyesuaian' };
const STATUS_LABEL = {
    PEMBELIAN: 'Pembelian',
    REFUND: 'Refund',
    TERJUAL: 'Terjual',
    BONUS: 'Bonus',
    HUTANG: 'Hutang',
    PENYESUAIAN: 'Penyesuaian',
};

const STATUS_OPTIONS_IN = [
    { id: 'PEMBELIAN', label: 'Pembelian' },
    { id: 'REFUND', label: 'Refund' },
];

const STATUS_OPTIONS_OUT = [
    { id: 'TERJUAL', label: 'Terjual' },
    { id: 'BONUS', label: 'Bonus' },
    { id: 'HUTANG', label: 'Hutang' },
];

const STATUS_OPTIONS_ADJUSTMENT = [
    { id: 'PENYESUAIAN', label: 'Penyesuaian' },
];

const statusOptionsForType = computed(() => {
    if (form.type === 'adjustment') return STATUS_OPTIONS_ADJUSTMENT;
    return form.type === 'out' ? STATUS_OPTIONS_OUT : STATUS_OPTIONS_IN;
});

const selectType = (type) => {
    form.type = type;
    if (type === 'adjustment') { form.status = 'PENYESUAIAN'; }
    else if (form.status === 'PENYESUAIAN') { form.status = type === 'out' ? 'TERJUAL' : 'PEMBELIAN'; }
};

const todayDate = new Date().toISOString().slice(0, 10);

const filterForm = reactive({
    period: { from: props.filters.from ?? todayDate, to: props.filters.to ?? todayDate },
    type:   props.filters.type ?? 'all',
    status: props.filters.status ?? 'all',
});

const STATUS_FILTER_OPTIONS = [
    { id: 'all', label: 'Semua status' },
    ...Object.entries(STATUS_LABEL).map(([id, label]) => ({ id, label })),
];

const hasActiveFilter = computed(() =>
    filterForm.type !== 'all'
    || filterForm.status !== 'all'
    || filterForm.period.from !== todayDate
    || filterForm.period.to !== todayDate
);

const resetFilter = () => {
    filterForm.period = { from: todayDate, to: todayDate };
    filterForm.type = 'all';
    filterForm.status = 'all';
    applyFilter();
};

const applyFilter = () => {
    router.get(route('stock-movements.index'), {
        from:   filterForm.period.from || undefined,
        to:     filterForm.period.to || undefined,
        type:   filterForm.type === 'all' ? undefined : filterForm.type,
        status: filterForm.status === 'all' ? undefined : filterForm.status,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const itemSearch = ref('');
const itemOpen = ref(false);
const selectedItem = ref(null);

const itemResults = computed(() => {
    const q = itemSearch.value.trim().toLowerCase();
    if (!q) return [];
    return props.items.filter((item) => item.label.toLowerCase().includes(q));
});

const selectItem = (option) => {
    selectedItem.value = option;
    form.item = option.key;
    itemSearch.value = '';
    itemOpen.value = false;
    errors.value = { ...errors.value, item: undefined };
};

const clearItem = () => {
    selectedItem.value = null;
    form.item = '';
};

const showModal = ref(false);
const saving = ref(false);
const errors = ref({});
const today = () => new Date().toLocaleDateString('en-CA');
const form = reactive({ item: '', type: 'in', status: 'PEMBELIAN', movement_date: today(), quantity: '', reference: '', notes: '' });

const openCreate = () => {
    Object.assign(form, { item: '', type: 'in', status: 'PEMBELIAN', movement_date: today(), quantity: '', reference: '', notes: '' });
    selectedItem.value = null;
    itemSearch.value = '';
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const validate = () => {
    const e = {};
    if (!form.item) e.item = 'Pilih item.';
    if (!form.movement_date) e.movement_date = 'Pilih tanggal.';
    if (!form.quantity || Number(form.quantity) < 1) e.quantity = 'Jumlah minimal 1.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const submit = () => {
    if (!validate()) return;
    const [itemType, itemId] = form.item.split(':');
    saving.value = true;
    router.post(route('stock-movements.store'), {
        item_type: itemType,
        item_id: Number(itemId),
        type: form.type,
        status: form.status,
        movement_date: form.movement_date,
        quantity: Number(form.quantity),
        reference: form.reference || null,
        notes: form.notes || null,
    }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (serverErrors) => toast.error(Object.values(serverErrors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    });
};

const editingMovement = ref(null);
const editForm = reactive({ movement_date: '', notes: '' });
const editErrors = ref({});
const editSaving = ref(false);

const openEdit = (movement) => {
    editingMovement.value = movement;
    Object.assign(editForm, { movement_date: movement.date, notes: movement.notes ?? '' });
    editErrors.value = {};
};

const closeEdit = () => {
    editingMovement.value = null;
};

const submitEdit = () => {
    if (!editForm.movement_date) {
        editErrors.value = { movement_date: 'Pilih tanggal.' };
        return;
    }
    editErrors.value = {};
    editSaving.value = true;
    router.patch(route('stock-movements.update', editingMovement.value.id), {
        movement_date: editForm.movement_date,
        notes: editForm.notes || null,
    }, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
        onError: (serverErrors) => { editErrors.value = serverErrors; },
        onFinish: () => { editSaving.value = false; },
    });
};

const handleDelete = async (movement) => {
    const ok = await confirm.confirm({
        title: 'Hapus mutasi?',
        message: `Mutasi ${TYPE_LABEL[movement.type] ?? movement.type} ${movement.quantity} untuk "${movement.item_name}" akan dihapus dan stok dikembalikan.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('stock-movements.destroy', movement.id), {
        preserveScroll: true,
        onError: (serverErrors) => toast.error(Object.values(serverErrors)[0] ?? 'Mutasi tidak bisa dihapus.'),
    });
};

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';
const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError ? 'border-red-400 dark:border-red-500 focus:ring-red-400' : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];

// Mobile action drawer
const showActionDrawer = ref(false);
const actionDrawerItem = ref(null);

const openActionDrawer = (movement) => {
    actionDrawerItem.value = movement;
    showActionDrawer.value = true;
};

const closeActionDrawer = () => {
    showActionDrawer.value = false;
    actionDrawerItem.value = null;
};

const getDrawerActions = (movement) => {
    const actions = [];

    // Lihat Detail
    actions.push({
        label: 'Lihat Detail',
        icon: Eye,
        onClick: () => {
            closeActionDrawer();
            router.visit(route('stock-movements.show', movement.id));
        },
    });

    // Divider
    actions.push({ divider: true });

    // Edit
    if (can('edit stock_movement')) {
        actions.push({
            label: 'Edit',
            icon: Pencil,
            onClick: () => {
                closeActionDrawer();
                openEdit(movement);
            },
        });
    }

    // Divider before delete
    if (can('delete stock_movement')) {
        actions.push({ divider: true });

        actions.push({
            label: 'Hapus',
            icon: Trash2,
            class: 'text-red-600 dark:text-red-400',
            onClick: () => {
                closeActionDrawer();
                handleDelete(movement);
            },
        });
    }

    return actions;
};
</script>

<template>
    <Head title="Mutasi Stok" />

    <AppLayout title="Mutasi Stok">
        <template v-if="can('add stock_movement')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Catat Mutasi" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <AppPageHeader title="Mutasi Stok" description="Catatan stok masuk, keluar, dan penyesuaian." class="shrink-0">
            <template #actions>
                <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode</label>
                        <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipe</label>
                        <AppDropdown v-model="filterForm.type" :options="TYPE_OPTIONS" @update:model-value="applyFilter" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <AppDropdown v-model="filterForm.status" :options="STATUS_FILTER_OPTIONS" @update:model-value="applyFilter" />
                    </div>
                </AppFilterModal>
                <AppButton v-if="can('add stock_movement')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Catat Mutasi
                </AppButton>
            </template>
        </AppPageHeader>

        <AppCard class="mb-5 hidden sm:block">
            <div class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-80">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Periode</label>
                    <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                </div>
                <div class="w-44">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipe</label>
                    <AppDropdown v-model="filterForm.type" :options="TYPE_OPTIONS" @update:model-value="applyFilter" />
                </div>
                <div class="w-44">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <AppDropdown v-model="filterForm.status" :options="STATUS_FILTER_OPTIONS" @update:model-value="applyFilter" />
                </div>
            </div>
        </AppCard>

        <AppTable :is-empty="rows.length === 0" empty-message="Belum ada mutasi stok." class="hidden sm:flex flex-1 min-h-0">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/60">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipe</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jumlah</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stok</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Referensi</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Oleh</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="px-5 py-3 text-sm text-gray-500">{{ item.date }}</td>
                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ item.item_name }}</td>
                    <td class="px-5 py-3">
                        <AppBadge :color="TYPE_COLOR[item.type] ?? 'gray'" size="sm" dot>{{ TYPE_LABEL[item.type] ?? item.type }}</AppBadge>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-500">{{ STATUS_LABEL[item.status] ?? item.status }}</td>
                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300 tabular-nums">{{ item.quantity }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500 tabular-nums">{{ item.stock_before }} → {{ item.stock_after }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">{{ item.reference ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500">{{ item.user_name ?? '-' }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-1">
                            <AppButton v-if="can('edit stock_movement')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(item)">
                                <Pencil class="h-4 w-4" />
                            </AppButton>
                            <AppButton v-if="can('delete stock_movement')" variant="ghost" size="sm" icon-only title="Hapus"
                                class="hover:!text-red-500 dark:hover:!text-red-400"
                                @click="handleDelete(item)">
                                <Trash2 class="h-4 w-4" />
                            </AppButton>
                        </div>
                    </td>
                </tr>
            </tbody>
            <template #pagination>
                <AppPagination :paginator="movements" />
            </template>

        </AppTable>

        <!-- MOBILE: card list with action drawer -->
        <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-2 -mx-4 px-4">
            <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                    <Layers class="h-6 w-6 text-gray-400" />
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada mutasi stok.</p>
            </div>

            <div
                v-for="item in rows"
                :key="item.id"
                class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 cursor-pointer transition-colors active:bg-gray-50 dark:active:bg-gray-800/50"
                @click="openActionDrawer(item)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ item.item_name }}</p>
                        <p class="text-xs text-gray-400">{{ item.date }}</p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <AppBadge :color="TYPE_COLOR[item.type] ?? 'gray'" size="sm" dot>{{ TYPE_LABEL[item.type] ?? item.type }}</AppBadge>
                        <span class="text-xs text-gray-500">{{ STATUS_LABEL[item.status] ?? item.status }}</span>
                    </div>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">{{ item.quantity }} pcs</span>
                    <span class="shrink-0 text-gray-400 tabular-nums">{{ item.stock_before }} → {{ item.stock_after }}</span>
                    <span v-if="item.reference" class="shrink-0 text-gray-400 truncate max-w-[120px]">{{ item.reference }}</span>
                </div>
            </div>
        </div>

        <div v-if="movements.total > 0" class="mt-4 sm:hidden">
            <AppPagination :paginator="movements" />
        </div>

        <AppModal :show="showModal" title="Catat Mutasi Stok" size="md" @close="closeModal">
            <form class="space-y-4" novalidate @submit.prevent="submit">
                <div>
                    <label :class="labelClass">Item <span class="text-red-500">*</span></label>
                    <div v-if="selectedItem" class="flex items-center gap-3 rounded-xl border border-primary-300 dark:border-primary-700 bg-primary-50/60 dark:bg-primary-900/20 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ selectedItem.label }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">Stok saat ini: {{ selectedItem.stock }}</p>
                        </div>
                        <button type="button" @click="clearItem"
                            class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                            aria-label="Hapus item">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div v-else class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="itemSearch"
                            type="text"
                            placeholder="Cari nama atau SKU item..."
                            @focus="itemOpen = true"
                            @blur="() => setTimeout(() => itemOpen = false, 150)"
                            :class="[inputClass(!!errors.item), 'pl-9']"
                        />
                        <div v-if="itemOpen && itemSearch.trim()"
                            class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl max-h-64 overflow-y-auto">
                            <button v-for="option in itemResults" :key="option.key" type="button"
                                @mousedown.prevent @click="selectItem(option)"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ option.label }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">Stok: {{ option.stock }}</p>
                                </div>
                            </button>
                            <div v-if="itemResults.length === 0" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada item cocok.
                            </div>
                        </div>
                    </div>
                    <p v-if="errors.item" class="mt-1.5 text-xs text-red-500">{{ errors.item }}</p>
                </div>
                <div>
                    <label :class="labelClass">Tanggal <span class="text-red-500">*</span></label>
                    <AppDatePicker v-model="form.movement_date" :error="!!errors.movement_date" />
                    <p v-if="errors.movement_date" class="mt-1.5 text-xs text-red-500">{{ errors.movement_date }}</p>
                </div>
                <div>
                    <label :class="labelClass">Tipe <span class="text-red-500">*</span></label>
                    <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800/60">
                        <button
                            v-for="opt in TYPE_FORM_OPTIONS"
                            :key="opt.id"
                            type="button"
                            @click="selectType(opt.id)"
                            :class="[
                                'inline-flex min-w-0 flex-1 items-center justify-center rounded-md px-2 py-2 text-sm font-medium transition-colors',
                                form.type === opt.id
                                    ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                    : 'text-gray-500 dark:text-gray-400',
                            ]"
                        >
                            <span class="truncate">{{ opt.label }}</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label :class="labelClass">Status <span class="text-red-500">*</span></label>
                    <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800/60">
                        <button
                            v-for="opt in statusOptionsForType"
                            :key="opt.id"
                            type="button"
                            @click="form.status = opt.id"
                            :class="[
                                'inline-flex min-w-0 flex-1 items-center justify-center rounded-md px-2 py-2 text-sm font-medium transition-colors',
                                form.status === opt.id
                                    ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                    : 'text-gray-500 dark:text-gray-400',
                            ]"
                        >
                            <span class="truncate">{{ opt.label }}</span>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Status untuk tipe mutasi yang dipilih.</p>
                </div>
                <div>
                    <label :class="labelClass">Jumlah <span class="text-red-500">*</span></label>
                    <AppNumberInput v-model="form.quantity" :min="1" placeholder="0" :error="!!errors.quantity" />
                    <p v-if="errors.quantity" class="mt-1.5 text-xs text-red-500">{{ errors.quantity }}</p>
                </div>
                <div>
                    <label :class="labelClass">Referensi</label>
                    <input v-model="form.reference" type="text" placeholder="Contoh: Nota supplier" :class="inputClass(false)" />
                </div>
                <div>
                    <label :class="labelClass">Catatan</label>
                    <textarea v-model="form.notes" rows="2" :class="inputClass(false)"></textarea>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="closeModal">Batal</AppButton>
                <AppButton variant="primary" size="sm" :loading="saving" @click="submit">Simpan</AppButton>
            </template>
        </AppModal>

        <AppModal :show="editingMovement !== null" title="Edit Mutasi Stok" size="sm" @close="closeEdit">
            <form class="space-y-4" novalidate @submit.prevent="submitEdit">
                <div>
                    <label :class="labelClass">Tanggal <span class="text-red-500">*</span></label>
                    <AppDatePicker v-model="editForm.movement_date" :error="!!editErrors.movement_date" />
                    <p v-if="editErrors.movement_date" class="mt-1.5 text-xs text-red-500">{{ editErrors.movement_date }}</p>
                </div>
                <div>
                    <label :class="labelClass">Catatan</label>
                    <textarea v-model="editForm.notes" rows="2" :class="inputClass(false)"></textarea>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tipe dan jumlah tidak bisa diubah. Hapus mutasi lalu catat ulang jika perlu.</p>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="closeEdit">Batal</AppButton>
                <AppButton variant="primary" size="sm" :loading="editSaving" @click="submitEdit">Simpan</AppButton>
            </template>
        </AppModal>

        <AppActionDrawer
            :show="showActionDrawer"
            :title="actionDrawerItem ? actionDrawerItem.item_name : 'Aksi'"
            :item="actionDrawerItem"
            :actions="actionDrawerItem ? getDrawerActions(actionDrawerItem) : []"
            @close="closeActionDrawer"
        />
    </AppLayout>
</template>
