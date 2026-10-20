<script setup>
import { usePermission } from '@/composables/usePermission';
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Truck, Inbox, X, Trash2, Eye } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppActionDrawer from '@/Components/UI/AppActionDrawer.vue';
import AppConfirmModal from '@/Components/UI/AppConfirmModal.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const { can } = usePermission();

const props = defineProps({
    purchases: { type: Object, required: true },
    filters:   { type: Object, default: () => ({}) },
});

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

const rows = computed(() => props.purchases.data);

const statusOptions = [
    { id: '', label: 'Semua status' },
    { id: 'lunas', label: 'Lunas' },
    { id: 'hutang', label: 'Hutang' },
];

const orderStatusOptions = [
    { id: '', label: 'Semua progres' },
    { id: 'ordered', label: 'Ordered' },
    { id: 'pending', label: 'Pending' },
    { id: 'done',    label: 'Done' },
];

const today = new Date().toISOString().slice(0, 10);

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    orderStatus: props.filters.order_status ?? '',
    range: {
        from: props.filters.from ?? today,
        to: props.filters.to ?? today,
    },
});

const hasActiveFilter = computed(() =>
    filterForm.status !== ''
    || filterForm.orderStatus !== ''
    || filterForm.range.from !== today
    || filterForm.range.to !== today
);

const applyFilter = () => {
    router.get(route('transaksi.pembelian'), {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
        order_status: filterForm.orderStatus || undefined,
        from: filterForm.range.from || undefined,
        to: filterForm.range.to || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let searchTimer = null;

watch(() => filterForm.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 300);
});

watch(() => filterForm.status, applyFilter);
watch(() => filterForm.orderStatus, applyFilter);
watch(() => filterForm.range, applyFilter, { deep: true });

const resetFilter = () => {
    filterForm.status = '';
    filterForm.orderStatus = '';
    filterForm.range = { from: today, to: today };
};

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const formatDateTime = (value) => (value
    ? new Date(String(value).replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const statusColor = { lunas: 'green', hutang: 'yellow' };

const orderStatusColor = { ordered: 'blue', pending: 'yellow', done: 'green' };

const orderStatusLabel = { ordered: 'Ordered', pending: 'Pending', done: 'Done' };

const deletePurchase = async (row) => {
    const ok = await confirm.confirm({
        title: `Hapus ${row.invoice}?`,
        message: 'Stok akan dikembalikan & mutasi terkait dihapus. Tindakan ini permanen.',
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('transaksi.pembelian.destroy', row.id), { preserveScroll: true });
};

// Mobile action drawer
const showActionDrawer = ref(false);
const actionDrawerItem = ref(null);

const openActionDrawer = (row) => {
    actionDrawerItem.value = row;
    showActionDrawer.value = true;
};

const closeActionDrawer = () => {
    showActionDrawer.value = false;
    actionDrawerItem.value = null;
};

const getDrawerActions = (row) => {
    const actions = [];

    // Lihat Detail
    actions.push({
        label: 'Lihat Detail',
        icon: Eye,
        onClick: () => {
            closeActionDrawer();
            router.visit(row.show_url);
        },
    });

    // Divider before delete
    if (can('delete purchase')) {
        actions.push({ divider: true });

        actions.push({
            label: 'Hapus',
            icon: Trash2,
            class: 'text-red-600 dark:text-red-400',
            onClick: () => {
                closeActionDrawer();
                deletePurchase(row);
            },
        });
    }

    return actions;
};

const columns = [
    { key: 'no', label: '#' },
    { key: 'invoice', label: 'No. Invoice' },
    { key: 'date', label: 'Tanggal' },
    { key: 'source_name', label: 'Supplier / Customer' },
    { key: 'order_status', label: 'Progres' },
    { key: 'total', label: 'Total' },
    { key: 'status', label: 'Status' },
    { key: 'aksi', label: '' },
];
</script>

<template>
    <Head title="Transaksi Pembelian" />

    <AppLayout title="Pembelian">

        <template v-if="can('add purchase')" #fab>
            <Link :href="route('transaksi.pembelian.create')">
                <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Pembelian">
                    <Plus class="h-6 w-6" />
                </AppButton>
            </Link>
        </template>

        <!-- App shell: header diam, hanya list yang scroll -->
        <div class="flex h-full flex-col">

            <AppPageHeader title="Pembelian" description="Transaksi pembelian / purchase order dari supplier." class="shrink-0">
                <template #search>
                    <div class="flex w-full items-center gap-2">
                        <div class="relative flex-1 sm:flex-none">
                            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari invoice..."
                                class="w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>
                        <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status Pembayaran</label>
                                    <AppDropdown v-model="filterForm.status" :options="statusOptions" placeholder="Semua status" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Progres</label>
                                    <AppDropdown v-model="filterForm.orderStatus" :options="orderStatusOptions" placeholder="Semua progres" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rentang Tanggal</label>
                                    <AppDateRangePicker v-model="filterForm.range" />
                                </div>
                            </div>
                        </AppFilterModal>
                    </div>
                </template>

                <template #actions>
                    <Link v-if="can('add purchase')" :href="route('transaksi.pembelian.create')" class="hidden w-full sm:flex sm:w-auto">
                        <AppButton variant="primary" size="md" class="w-full sm:w-auto">
                            <Plus class="h-4 w-4" />
                            Tambah Pembelian
                        </AppButton>
                    </Link>
                </template>
            </AppPageHeader>

            <div class="hidden shrink-0 mb-4 sm:flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-44">
                    <AppDropdown v-model="filterForm.status" :options="statusOptions" placeholder="Semua status" />
                </div>
                <div class="w-full sm:w-44">
                    <AppDropdown v-model="filterForm.orderStatus" :options="orderStatusOptions" placeholder="Semua progres" />
                </div>
                <div class="w-full sm:w-72">
                    <AppDateRangePicker v-model="filterForm.range" />
                </div>
                <AppButton v-if="hasActiveFilter" variant="ghost" size="sm" @click="resetFilter">
                    <X class="h-4 w-4" />
                    Reset filter
                </AppButton>
            </div>

            <!-- DESKTOP (sm+): tabel -->
            <AppTable :is-empty="rows.length === 0" class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key"
                            class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(row, i) in rows" :key="row.id"
                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                        @click="router.visit(row.show_url)">
                        <td class="px-5 py-3 text-sm text-gray-400">{{ i + 1 }}</td>
                        <td class="px-5 py-3">
                            <Link :href="row.show_url" class="font-mono text-sm font-medium text-primary-700 hover:underline dark:text-primary-400" @click.stop>{{ row.invoice }}</Link>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ formatDateTime(row.date) }}</td>
                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.source_name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="orderStatusColor[row.status] ?? 'gray'" size="sm">{{ orderStatusLabel[row.status] ?? row.status }}</AppBadge>
                        </td>
                        <td class="px-5 py-3 text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ formatRupiah(row.total) }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="statusColor[row.payment_status] ?? 'gray'" size="sm" dot>{{ row.payment_status }}</AppBadge>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <AppButton v-if="can('delete purchase')" variant="ghost" size="sm" icon-only title="Hapus" @click.stop="deletePurchase(row)">
                                <Trash2 class="h-4 w-4" />
                            </AppButton>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="purchases" />
            </template>

            </AppTable>

            <!-- MOBILE: card list -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Inbox class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada pembelian ditemukan.</p>
                </div>

                <div
                    v-for="row in rows"
                    :key="row.id"
                    class="block rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm transition-colors active:bg-gray-50 dark:active:bg-gray-800/50 cursor-pointer"
                    @click="openActionDrawer(row)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono font-semibold text-gray-900 dark:text-white text-sm truncate">{{ row.invoice }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">{{ formatDateTime(row.date) }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <AppBadge :color="orderStatusColor[row.status] ?? 'gray'" size="sm">{{ orderStatusLabel[row.status] ?? row.status }}</AppBadge>
                            <AppBadge :color="statusColor[row.payment_status] ?? 'gray'" size="sm" dot>{{ row.payment_status }}</AppBadge>
                        </div>
                    </div>

                    <dl class="mt-3 space-y-1">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <dt class="text-gray-400">Supplier</dt>
                            <dd class="text-gray-600 dark:text-gray-300">{{ row.source_name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <dt class="text-gray-400">Total</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ formatRupiah(row.total) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="purchases.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="purchases" />
            </div>

            <AppConfirmModal />
        </div>
    </AppLayout>

    <!-- Mobile Action Drawer -->
    <AppActionDrawer
        :show="showActionDrawer"
        :title="actionDrawerItem ? actionDrawerItem.invoice : 'Aksi'"
        :item="actionDrawerItem"
        :actions="actionDrawerItem ? getDrawerActions(actionDrawerItem) : []"
        @close="closeActionDrawer"
    />
</template>
