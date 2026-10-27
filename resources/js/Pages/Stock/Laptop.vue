<script setup>
import { usePermission } from '@/composables/usePermission';
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, Laptop, X, Printer, Eye, FileDown } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppActionDrawer from '@/Components/UI/AppActionDrawer.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import LaptopFormModal from '@/Components/Laptops/LaptopFormModal.vue';
import { useToast } from '@/composables/useToast';
import { useStockFilter } from '@/composables/useStockFilter';
import AppSortToggle from '@/Components/UI/AppSortToggle.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useItemPriceTag } from '@/composables/useItemPriceTag';

const { can } = usePermission();

const props = defineProps({
    laptops:   { type: Object, required: true },
    filters:   { type: Object, default: () => ({}) },
    brands:    { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
});

const toast   = useToast();
const confirm = useConfirm();
const page    = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const rows = computed(() => props.laptops.data);

const laptopStatuses = ['ON PROSES', 'SIAP JUAL', 'DISPLAY', 'PENDING', 'KENDALA', 'HABIS'];

const { form: filterForm, hasActiveFilter, resetFilter } = useStockFilter(
    'stock.laptop',
    props.filters,
    ['brand_id', 'supplier_id', 'status'],
);

const brandFilterOptions = computed(() => [{ id: '', label: 'Semua brand' }, ...props.brands]);
const supplierFilterOptions = computed(() => [{ id: '', label: 'Semua supplier' }, ...props.suppliers]);
const statusFilterOptions = computed(() => [{ id: '', label: 'Semua status' }, ...laptopStatuses]);

const showModal    = ref(false);
const selectedItem = ref(null);
const modalRef     = ref(null);

const openCreate = () => { selectedItem.value = null; showModal.value = true; };
const openEdit   = (item) => { selectedItem.value = { ...item }; showModal.value = true; };
const closeModal = () => { showModal.value = false; selectedItem.value = null; };

const handleSubmit = (payload) => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError:   (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish:  () => modalRef.value?.stopLoading(),
    };

    if (selectedItem.value) {
        router.put(route('stock.laptop.update', selectedItem.value.id), payload, options);
    } else {
        router.post(route('stock.laptop.store'), payload, options);
    }
};

const { addItemToPriceTag } = useItemPriceTag();

const printPriceTag = (item) => {
    addItemToPriceTag(item);
    toast.success(`${item.name} ditambahkan ke Price Tag.`);
};

// Export laporan laptop (lokal / supplier) dalam bentuk PDF.
const exportOpen       = ref(false);
const exportJenis      = ref('lokal');
const exportLocalStatus = ref('jual_display');
const exportSupplierStock = ref('all');

const exportStatusOptions = [
    { id: 'jual_display', label: 'SIAP JUAL & DISPLAY' },
    { id: 'on_process',   label: 'ON PROSES' },
    { id: 'pending',      label: 'PENDING' },
    { id: 'kendala',      label: 'KENDALA' },
    { id: 'habis',        label: 'HABIS' },
];

const exportSupplierStockOptions = [
    { id: 'all',       label: 'Semua' },
    { id: 'available', label: 'Masih ada stok' },
    { id: 'empty',     label: 'Stok habis' },
];

const openExport = () => {
    exportJenis.value = 'lokal';
    exportLocalStatus.value = 'jual_display';
    exportSupplierStock.value = 'all';
    exportOpen.value = true;
};

const doExport = () => {
    const params = exportJenis.value === 'supplier'
        ? { jenis: 'supplier', stock: exportSupplierStock.value }
        : { jenis: 'lokal', status: exportLocalStatus.value };

    window.open(route('stock.laptop.export', params), '_blank', 'noopener');
    exportOpen.value = false;
};

const handleDelete = async (item) => {
    const ok = await confirm.confirm({
        title: 'Hapus laptop?',
        message: `Laptop "${item.name}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('stock.laptop.destroy', item.id), { preserveScroll: true });
};

const formatRupiah = (value) =>
    value == null || value === '' ? '—' : `Rp ${Number(value).toLocaleString('id-ID')}`;

const statusColors = {
    'SIAP JUAL': 'green',
    'ON PROSES': 'blue',
    'DISPLAY':   'purple',
    'PENDING':   'yellow',
    'KENDALA':   'red',
    'HABIS':     'gray',
};

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
            router.visit(route('stock.laptop.show', item.id));
        },
    });

    // Print Price Tag
    actions.push({
        label: 'Print Price Tag',
        icon: Printer,
        onClick: () => {
            closeActionDrawer();
            printPriceTag(item);
        },
    });

    // Divider
    actions.push({ divider: true });

    // Edit
    if (can('edit laptop')) {
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
    if (can('delete laptop')) {
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
</script>

<template>
    <Head title="Stok Laptop" />

    <AppLayout title="Stok Laptop">

        <template v-if="can('add laptop')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Laptop" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <!-- App shell: header (search/tombol) diam, hanya list yang scroll -->
        <div class="flex h-full flex-col">

            <AppPageHeader title="Stok Laptop" description="Data stok laptop yang tersedia di gudang." class="shrink-0">
                <template #search>
                    <div class="flex w-full items-center gap-2">
                        <div class="relative flex-1 sm:flex-none">
                            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari nama, SKU, kode, atau serial..."
                                class="w-full sm:w-72 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>
                        <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
                                <AppSelect v-model="filterForm.brand_id" :options="brandFilterOptions" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Supplier</label>
                                <AppSelect v-model="filterForm.supplier_id" :options="supplierFilterOptions" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <AppDropdown v-model="filterForm.status" :options="statusFilterOptions" placeholder="Semua status" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Urutkan SKU</label>
                                <AppSortToggle :model-value="filterForm.sort" @update:model-value="filterForm.sort = $event" />
                            </div>
                        </AppFilterModal>
                    </div>
                </template>

                <template #actions>
                    <AppButton v-if="can('add laptop')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Laptop
                    </AppButton>
                </template>
            </AppPageHeader>

            <div class="hidden shrink-0 mb-4 sm:flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-56">
                    <AppSelect v-model="filterForm.brand_id" :options="brandFilterOptions" />
                </div>
                <div class="w-full sm:w-56">
                    <AppSelect v-model="filterForm.supplier_id" :options="supplierFilterOptions" />
                </div>
                <div class="w-full sm:w-56">
                    <AppDropdown v-model="filterForm.status" :options="statusFilterOptions" placeholder="Semua status" />
                </div>
                <div class="w-full sm:w-56">
                    <AppSortToggle :model-value="filterForm.sort" @update:model-value="filterForm.sort = $event" />
                </div>
                <AppButton variant="outline" size="md" @click="openExport">
                    <FileDown class="h-4 w-4" />
                    Export
                </AppButton>
                <AppButton v-if="hasActiveFilter" variant="ghost" size="sm" @click="resetFilter">
                    <X class="h-4 w-4" />
                    Reset filter
                </AppButton>
            </div>

            <!-- DESKTOP VIEW (sm+): Tabel — hanya isi tabel yang scroll -->
            <AppTable :is-empty="rows.length === 0" class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap w-12">#</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Nama Produk</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Brand</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">SKU</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Kode</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Stok</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Harga Jual</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(item, i) in rows" :key="item.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 text-sm text-gray-400">{{ i + 1 }}</td>
                        <td class="px-5 py-3">
                            <Link :href="route('stock.laptop.show', item.id)" class="font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400 text-sm">{{ item.name }}</Link>
                            <p v-if="item.serialnumber" class="text-xs text-gray-400 font-mono">SN: {{ item.serialnumber }}</p>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ item.brand_name ?? '—' }}</td>
                        <td class="px-5 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">{{ item.sku }}</td>
                        <td class="px-5 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">{{ item.code ?? '—' }}</td>
                        <td class="px-5 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ item.stock }}</td>
                        <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ formatRupiah(item.sell_price) }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="statusColors[item.status] ?? 'gray'" size="sm" dot>{{ item.status }}</AppBadge>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                <AppButton variant="ghost" size="sm" icon-only title="Print Price Tag" @click="printPriceTag(item)">
                                    <Printer class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('edit laptop')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('delete laptop')" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(item)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="laptops" />
            </template>

            </AppTable>

            <!-- MOBILE VIEW: Card list — hanya list ini yang scroll -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Laptop class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada laptop ditemukan.</p>
                </div>

                <div
                    v-for="item in rows"
                    :key="item.id"
                    class="flex items-start justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3.5 py-3 shadow-sm cursor-pointer active:bg-gray-50 dark:active:bg-gray-800/50"
                    @click="openActionDrawer(item)"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ item.brand_name ?? '—' }} · {{ item.sku }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span v-if="item.code" class="inline-flex items-center rounded-full bg-orange-100 dark:bg-orange-500/10 px-2 py-0.5 font-mono text-[10px] font-medium text-orange-700 dark:text-orange-400">{{ item.code }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="Number(item.stock ?? 0) > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'">
                                Stok {{ item.stock ?? 0 }}
                            </span>
                            <AppBadge :color="statusColors[item.status] ?? 'gray'" size="sm">{{ item.status }}</AppBadge>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatRupiah(item.sell_price) }}</p>
                    </div>
                </div>
            </div>

            <div v-if="laptops.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="laptops" />
            </div>
        </div>

    </AppLayout>

    <LaptopFormModal
        ref="modalRef"
        :show="showModal"
        :item="selectedItem"
        :brands="brands"
        :suppliers="suppliers"
        @close="closeModal"
        @submit="handleSubmit"
    />

    <!-- Mobile Action Drawer -->
    <AppActionDrawer
        :show="showActionDrawer"
        :title="actionDrawerItem ? actionDrawerItem.name : 'Aksi'"
        :item="actionDrawerItem"
        :actions="actionDrawerItem ? getDrawerActions(actionDrawerItem) : []"
        @close="closeActionDrawer"
    />

    <!-- Export Laporan -->
    <AppModal :show="exportOpen" title="Export Laporan Laptop" size="md" @close="exportOpen = false">
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Jenis</label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="option in [{ id: 'lokal', label: 'Laptop Lokal' }, { id: 'supplier', label: 'Laptop Supplier' }]"
                        :key="option.id"
                        type="button"
                        class="rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors"
                        :class="exportJenis === option.id
                            ? 'border-primary-600 bg-primary-600 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                        @click="exportJenis = option.id"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div v-if="exportJenis === 'lokal'">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <AppDropdown v-model="exportLocalStatus" :options="exportStatusOptions" placeholder="Pilih status" />
            </div>
            <div v-else>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Stok</label>
                <AppDropdown v-model="exportSupplierStock" :options="exportSupplierStockOptions" placeholder="Pilih stok" />
            </div>
        </div>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="exportOpen = false">Batal</AppButton>
            <AppButton variant="primary" size="sm" @click="doExport">Export</AppButton>
        </template>
    </AppModal>
</template>
