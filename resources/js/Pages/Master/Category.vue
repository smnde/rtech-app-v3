<script setup>
import { usePermission } from '@/composables/usePermission';
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, Tag } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import CategoryFormModal from '@/Components/Categories/CategoryFormModal.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const { can } = usePermission();

const props = defineProps({
    categories: { type: Object, required: true },
    filters:    { type: Object, default: () => ({}) },
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

const rows = computed(() => props.categories.data);

const search = ref(props.filters.search ?? '');
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('master.category'), { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const showModal        = ref(false);
const selectedCategory = ref(null);
const modalRef         = ref(null);

const openCreate = () => { selectedCategory.value = null; showModal.value = true; };
const openEdit   = (cat) => { selectedCategory.value = { ...cat }; showModal.value = true; };
const closeModal = () => { showModal.value = false; selectedCategory.value = null; };

const handleSubmit = (payload) => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError:   (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish:  () => modalRef.value?.stopLoading(),
    };

    if (selectedCategory.value) {
        router.put(route('master.category.update', selectedCategory.value.id), payload, options);
    } else {
        router.post(route('master.category.store'), payload, options);
    }
};

const handleDelete = async (cat) => {
    const ok = await confirm.confirm({
        title: 'Hapus kategori?',
        message: `Kategori "${cat.name}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('master.category.destroy', cat.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Kategori" />

    <AppLayout title="Kategori">

        <template v-if="can('add category')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Kategori" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <!-- App shell: header (search/tombol) diam, hanya list yang scroll -->
        <div class="flex h-full flex-col">

            <AppPageHeader title="Kategori" description="Kelola kategori produk yang tersedia di sistem." class="shrink-0">
                <template #actions>
                    <AppButton v-if="can('add category')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Kategori
                    </AppButton>
                </template>
                <template #search>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari kategori..."
                            class="w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                </template>

            </AppPageHeader>

            <!-- DESKTOP VIEW (sm+): Tabel — hanya isi tabel yang scroll -->
            <AppTable :is-empty="rows.length === 0" class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap w-12">#</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Nama Kategori</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Dibuat</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(cat, i) in rows" :key="cat.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 text-sm text-gray-400">{{ i + 1 }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400">
                                    <Tag class="h-4 w-4" />
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ cat.name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ cat.created_at }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                <AppButton v-if="can('edit category')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(cat)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('delete category')" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(cat)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="categories" />
            </template>

            </AppTable>

            <!-- MOBILE VIEW: Card list — hanya list ini yang scroll -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Tag class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada kategori ditemukan.</p>
                </div>

                <div
                    v-for="cat in rows"
                    :key="cat.id"
                    class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3.5 py-2 shadow-sm"
                >
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400">
                        <Tag class="h-3.5 w-3.5" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ cat.name }}</p>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <AppButton v-if="can('edit category')" variant="ghost" size="sm" icon-only @click="openEdit(cat)">
                            <Pencil class="h-4 w-4" />
                        </AppButton>
                        <AppButton v-if="can('delete category')" variant="ghost" size="sm" icon-only
                            class="hover:!text-red-500 dark:hover:!text-red-400"
                            @click="handleDelete(cat)">
                            <Trash2 class="h-4 w-4" />
                        </AppButton>
                    </div>
                </div>
            </div>

            <div v-if="categories.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="categories" />
            </div>
        </div>

    </AppLayout>

    <CategoryFormModal
        ref="modalRef"
        :show="showModal"
        :category="selectedCategory"
        @close="closeModal"
        @submit="handleSubmit"
    />
</template>
