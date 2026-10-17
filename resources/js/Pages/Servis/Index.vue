<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, Wrench, CheckCircle2, RefreshCw } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import ServisFormModal from '@/Components/Servis/ServisFormModal.vue';
import ServisConfirmModal from '@/Components/Servis/ServisConfirmModal.vue';
import ServisStatusModal from '@/Components/Servis/ServisStatusModal.vue';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    reparations: { type: Object, required: true },
    customers:   { type: Array, default: () => [] },
    statuses:    { type: Array, default: () => [] },
    filters:     { type: Object, default: () => ({}) },
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

const rows = computed(() => props.reparations.data);

const STATUS_COLOR = {
    RECEIVED: 'gray',
    CHECKED: 'blue',
    CONFIRMED: 'blue',
    'WAITING CONFIRMATION': 'yellow',
    'ON PROCESS': 'purple',
    PENDING: 'yellow',
    CANCEL: 'red',
    DONE: 'green',
    TAKEN: 'green',
};


const flagOptions = [
    { id: 'HW', label: 'HW' },
    { id: 'SW', label: 'SW' },
    { id: 'MB', label: 'MB' },
];

const deadlineFor = (item) => (['RECEIVED', 'CHECKED'].includes(item.status)
    ? { label: 'Deadline cek', value: item.check_deadline_at }
    : { label: 'Deadline proses', value: item.process_deadline_at });

const formatDeadline = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    : '—');

const deadlineColor = (value) => {
    if (!value) return 'gray';
    const target = new Date(value.replace(' ', 'T'));
    target.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = (target - today) / 86400000;
    if (diff < 0) return 'red';
    if (diff === 0) return 'yellow';
    if (diff === 1) return 'orange';
    return 'green';
};

const statusOptions = computed(() => props.statuses.map((status) => ({ id: status, label: status })));
const statusFilterOptions = computed(() => [{ id: '', label: 'Semua status' }, ...statusOptions.value]);
const flagFilterOptions = computed(() => [{ id: '', label: 'Semua kerusakan' }, ...flagOptions]);

const todayDate = new Date().toISOString().slice(0, 10);

const hasActiveFilter = computed(() =>
    filterForm.status !== ''
    || filterForm.flag !== ''
    || filterForm.period.from !== todayDate
    || filterForm.period.to !== todayDate
);

const resetFilter = () => {
    filterForm.status = '';
    filterForm.flag = '';
    filterForm.period = { from: todayDate, to: todayDate };
    applyFilter();
};

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    flag: props.filters.flag ?? '',
    period: { from: props.filters.from ?? todayDate, to: props.filters.to ?? todayDate },
});

const applyFilter = () => {
    router.get(route('servis.index'), {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
        flag: filterForm.flag || undefined,
        from: filterForm.period.from || undefined,
        to: filterForm.period.to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

let searchTimer = null;
const onSearch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 300);
};

const showModal = ref(false);
const selected = ref(null);
const saving = ref(false);

const openCreate = () => {
    selected.value = null;
    showModal.value = true;
};

const openEdit = (item) => {
    selected.value = item;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selected.value = null;
};

const submit = (payload) => {
    saving.value = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    };

    if (selected.value) {
        router.put(route('servis.update', selected.value.id), payload, options);
    } else {
        router.post(route('servis.store'), payload, options);
    }
};

const showConfirmModal = ref(false);
const showStatusModal = ref(false);
const actionTarget = ref(null);

const openConfirm = (item) => {
    actionTarget.value = item;
    showConfirmModal.value = true;
};

const openStatus = (item) => {
    actionTarget.value = item;
    showStatusModal.value = true;
};

const closeActionModals = () => {
    showConfirmModal.value = false;
    showStatusModal.value = false;
    actionTarget.value = null;
};

const submitConfirm = (payload) => {
    saving.value = true;
    router.patch(route('servis.confirm', actionTarget.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => closeActionModals(),
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    });
};

const submitStatus = (payload) => {
    saving.value = true;
    router.patch(route('servis.status', actionTarget.value.id), payload, {
        preserveScroll: true,
        onSuccess: () => closeActionModals(),
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    });
};

const createCustomer = (payload, done) => {
    router.post(route('customers.store'), payload, {
        preserveScroll: true,
        onSuccess: () => done(props.customers.find((c) => c.phone === payload.phone) ?? null),
        onError: (errors) => {
            toast.error(Object.values(errors)[0] ?? 'Customer tidak valid.');
            done(null);
        },
    });
};

const handleDelete = async (item) => {
    const ok = await confirm.confirm({
        title: 'Hapus servis?',
        message: `Servis "${item.item_name}" akan dihapus.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('servis.destroy', item.id), { preserveScroll: true });
};

const cardClass = 'rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900';
</script>

<template>
    <Head title="Servis" />

    <AppLayout title="Servis">
        <template v-if="can('add reparation')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Servis" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <div class="flex h-full flex-col">
            <AppPageHeader title="Servis" description="Servis laptop customer." class="shrink-0">
                <template #search>
                    <div class="flex w-full items-center gap-2">
                        <div class="relative flex-1 sm:flex-none">
                            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari laptop atau customer..."
                                class="w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                @input="onSearch"
                            />
                        </div>
                        <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <AppDropdown v-model="filterForm.status" :options="statusFilterOptions" placeholder="Semua status" @update:model-value="applyFilter" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kerusakan</label>
                                <AppDropdown v-model="filterForm.flag" :options="flagFilterOptions" placeholder="Semua kerusakan" @update:model-value="applyFilter" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode</label>
                                <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                            </div>
                        </AppFilterModal>
                    </div>
                </template>

                <template #actions>
                    <AppButton v-if="can('add reparation')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Servis
                    </AppButton>
                </template>
            </AppPageHeader>

            <div class="hidden shrink-0 mb-4 sm:flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-56">
                    <AppDropdown v-model="filterForm.status" :options="statusFilterOptions" placeholder="Semua status" @update:model-value="applyFilter" />
                </div>
                <div class="w-full sm:w-56">
                    <AppDropdown v-model="filterForm.flag" :options="flagFilterOptions" placeholder="Semua kerusakan" @update:model-value="applyFilter" />
                </div>
                <div class="w-full sm:w-72">
                    <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                </div>
            </div>

            <AppTable :is-empty="rows.length === 0" class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Laptop</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kerusakan</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deadline</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="item in rows" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 text-sm text-gray-500">{{ item.created_at }}</td>
                        <td class="px-5 py-3 text-sm font-mono">
                            <Link :href="route('servis.show', item.id)" class="text-primary-600 dark:text-primary-400 hover:underline">{{ item.invoice ?? '—' }}</Link>
                        </td>
                        <td class="px-5 py-3 text-sm">
                            <span class="font-medium text-gray-900 dark:text-white">{{ item.customer_name }}</span>
                            <p class="text-xs text-gray-500">{{ item.customer_phone }}</p>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ item.item_name }}</td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1">
                                <AppBadge v-for="flag in item.flag" :key="flag" color="blue" size="sm">{{ flag }}</AppBadge>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <AppBadge :color="deadlineColor(deadlineFor(item).value)" size="sm">
                                {{ formatDeadline(deadlineFor(item).value) }}
                            </AppBadge>
                        </td>
                        <td class="px-5 py-3">
                            <AppBadge :color="STATUS_COLOR[item.status] ?? 'gray'" size="sm">{{ item.status }}</AppBadge>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                <AppButton v-if="can('edit reparation') && item.status !== 'TAKEN' && item.status !== 'CANCEL'" variant="ghost" size="sm" icon-only title="Konfirmasi" @click="openConfirm(item)">
                                    <CheckCircle2 class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('edit reparation')" variant="ghost" size="sm" icon-only title="Ubah Status" @click="openStatus(item)">
                                    <RefreshCw class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('edit reparation')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('delete reparation')" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400" @click="handleDelete(item)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="reparations" />
            </template>

            </AppTable>

            <div class="scrollbar-styled scrollbar-mobile-hidden sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Wrench class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada servis.</p>
                </div>

                <Link
                    v-for="item in rows"
                    :key="item.id"
                    :href="route('servis.show', item.id)"
                    :class="[cardClass, 'block p-4 shadow-sm']"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ item.item_name }}</p>
                        <AppBadge :color="STATUS_COLOR[item.status] ?? 'gray'" size="sm">{{ item.status }}</AppBadge>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ item.invoice ?? '—' }} · {{ item.customer_name }} · {{ item.created_at }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <AppBadge v-for="flag in item.flag" :key="flag" color="blue" size="sm">{{ flag }}</AppBadge>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ (item.stages ?? []).flatMap((s) => s.items ?? []).map((i) => `${i.label}: ${i.cost}`).join(' · ') || 'Estimasi: —' }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <AppBadge :color="deadlineColor(deadlineFor(item).value)" size="sm">
                            {{ formatDeadline(deadlineFor(item).value) }}
                        </AppBadge>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-1 pt-3 border-t border-gray-100 dark:border-gray-800" @click.stop.prevent>
                        <AppButton v-if="can('edit reparation') && item.status !== 'TAKEN' && item.status !== 'CANCEL'" variant="ghost" size="sm" icon-only title="Konfirmasi" @click="openConfirm(item)">
                            <CheckCircle2 class="h-4 w-4" />
                        </AppButton>
                        <AppButton v-if="can('edit reparation')" variant="ghost" size="sm" icon-only title="Ubah Status" @click="openStatus(item)">
                            <RefreshCw class="h-4 w-4" />
                        </AppButton>
                        <AppButton v-if="can('edit reparation')" variant="ghost" size="sm" icon-only @click="openEdit(item)">
                            <Pencil class="h-4 w-4" />
                        </AppButton>
                        <AppButton v-if="can('delete reparation')" variant="ghost" size="sm" icon-only
                            class="hover:!text-red-500 dark:hover:!text-red-400" @click="handleDelete(item)">
                            <Trash2 class="h-4 w-4" />
                        </AppButton>
                    </div>
                </Link>
            </div>
            <div v-if="reparations.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="reparations" />
            </div>
        </div>
    </AppLayout>

    <ServisFormModal
        :show="showModal"
        :item="selected"
        :customers="customers"
        :statuses="statuses"
        @close="closeModal"
        @submit="submit"
        @customer-created="createCustomer"
    />

    <ServisConfirmModal
        v-if="actionTarget"
        :show="showConfirmModal"
        :reparation="actionTarget"
        :saving="saving"
        @close="closeActionModals"
        @submit="submitConfirm"
    />

    <ServisStatusModal
        v-if="actionTarget"
        :show="showStatusModal"
        :reparation="actionTarget"
        :statuses="statuses"
        :saving="saving"
        @close="closeActionModals"
        @submit="submitStatus"
    />
</template>
