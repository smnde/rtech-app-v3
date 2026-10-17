<script setup>
import { usePermission } from '@/composables/usePermission';
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, X, Pencil, Trash2, Headphones } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AccessoryFormModal from '@/Components/Accessories/AccessoryFormModal.vue';
import { useToast } from '@/composables/useToast';
import { useStockFilter } from '@/composables/useStockFilter';
import AppSortToggle from '@/Components/UI/AppSortToggle.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import { useConfirm } from '@/composables/useConfirm';

const { can } = usePermission();

const props = defineProps({
    accessories: { type: Object, required: true },
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

const rows = computed(() => props.accessories.data);

const { form: filterForm, hasActiveFilter, resetFilter } = useStockFilter(
    'stock.aksesoris',
    props.filters,
    ['brand_id', 'supplier_id', 'status'],
);

const brandFilterOptions = computed(() => [{ id: '', label: 'Semua brand' }, ...props.brands]);
const statusFilterOptions = computed(() => [{ id: '', label: 'Semua status' }, ...['READY STOCK', 'HABIS']]);
const supplierFilterOptions = computed(() => [{ id: '', label: 'Semua supplier' }, ...props.suppliers]);

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
        router.put(route('stock.aksesoris.update', selectedItem.value.id), payload, options);
    } else {
        router.post(route('stock.aksesoris.store'), payload, options);
    }
};

const handleDelete = async (item) => {
    const ok = await confirm.confirm({
        title: 'Hapus aksesoris?',
        message: `Aksesoris "${item.name}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('stock.aksesoris.destroy', item.id), { preserveScroll: true });
};

const formatRupiah = (value) =>
    value == null || value === '' ? '—' : `Rp ${Number(value).toLocaleString('id-ID')}`;

</script>

<template>
    <Head title="Stok Aksesoris" />

    <AppLayout title="Stok Aksesoris">

        <template v-if="can('add aksesoris')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Aksesoris" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <!-- App shell: header (search/tombol) diam, hanya list yang scroll -->
        <div class="flex h-full flex-col">

            <AppPageHeader title="Stok Aksesoris" description="Data stok aksesoris di gudang." class="shrink-0">
                <template #search>
                    <div class="flex w-full items-center gap-2">
                        <div class="relative flex-1 sm:flex-none">
                            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari nama atau SKU..."
                                class="w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
                    <AppButton v-if="can('add aksesoris')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Aksesoris
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
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Stok</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Harga Jual</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(item, i) in rows" :key="item.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 text-sm text-gray-400">{{ i + 1 }}</td>
                        <td class="px-5 py-3">
                            <Link :href="route('stock.aksesoris.show', item.id)" class="font-medium text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400 text-sm">{{ item.name }}</Link>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ item.brand_name ?? '—' }}</td>
                        <td class="px-5 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">{{ item.sku }}</td>
                        <td class="px-5 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ item.stock }}</td>
                        <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ formatRupiah(item.sell_price) }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                <AppButton v-if="can('edit aksesoris')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('delete aksesoris')" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(item)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="accessories" />
            </template>

            </AppTable>

            <!-- MOBILE VIEW: Card list — hanya list ini yang scroll -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Headphones class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada aksesoris ditemukan.</p>
                </div>

                <Link
                    v-for="item in rows"
                    :key="item.id"
                    :href="route('stock.aksesoris.show', item.id)"
                    class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3.5 py-2.5 shadow-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ item.brand_name ?? '—' }} · {{ item.sku }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs font-medium text-gray-900 dark:text-white">{{ formatRupiah(item.sell_price) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Stok {{ item.stock }}</p>
                    </div>
                </Link>
            </div>

            <div v-if="accessories.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="accessories" />
            </div>
        </div>

    </AppLayout>

    <AccessoryFormModal
        ref="modalRef"
        :show="showModal"
        :item="selectedItem"
        :brands="brands"
        :suppliers="suppliers"
        @close="closeModal"
        @submit="handleSubmit"
    />
</template>
