<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, BadgePercent } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import PromoFormModal from '@/Components/Promo/PromoFormModal.vue';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const { can } = usePermission();

const props = defineProps({
    promos:          { type: Object, required: true },
    filters:         { type: Object, default: () => ({}) },
    itemTypeOptions: { type: Array, default: () => [] },
});

const toast = useToast();
const { confirmDelete } = useDeleteConfirm();
const page = usePage();

const typeLabels = {
    laptop: 'Laptop',
    gadget: 'Gadget',
    accessory: 'Aksesoris',
    sparepart: 'Sparepart',
};

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const rows = computed(() => props.promos.data);

const search = ref(props.filters.search ?? '');
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('promo.index'), { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const formatRupiah = (value) => `Rp ${new Intl.NumberFormat('id-ID').format(Number(value) || 0)}`;

const formatValue = (promo) =>
    promo.type === 'percent' ? `${Number(promo.value)}%` : formatRupiah(promo.value);

const formatDate = (value) => {
    if (!value) return '—';
    const [year, month, day] = value.split('-');
    return `${day}/${month}/${year}`;
};

const showModal = ref(false);
const selectedPromo = ref(null);
const modalRef = ref(null);

const openCreate = () => { selectedPromo.value = null; showModal.value = true; };
const openEdit = (promo) => { selectedPromo.value = { ...promo }; showModal.value = true; };
const closeModal = () => { showModal.value = false; selectedPromo.value = null; };

const handleSubmit = (payload) => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => modalRef.value?.stopLoading(),
    };

    if (selectedPromo.value) {
        router.put(route('promo.update', selectedPromo.value.id), payload, options);
    } else {
        router.post(route('promo.store'), payload, options);
    }
};

const handleDelete = async (promo) => {
    await confirmDelete({
        url: route('promo.destroy', promo.id),
        title: 'Hapus promo?',
        message: `Promo "${promo.title}" akan dihapus permanen. Nota yang sudah dibuat tidak berubah.`,
        confirmLabel: 'Hapus',
        options: { preserveScroll: true },
    });
};
</script>

<template>
    <Head title="Promo" />

    <AppLayout title="Promo">
        <template v-if="can('add promo')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Promo" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <div class="flex h-full flex-col">
            <AppPageHeader title="Promo" description="Atur potongan harga yang berlaku pada rentang tanggal tertentu." class="shrink-0">
                <template #actions>
                    <AppButton v-if="can('add promo')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Promo
                    </AppButton>
                </template>
                <template #search>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari promo..."
                            class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-4 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 sm:w-64 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </template>
            </AppPageHeader>

            <!-- DESKTOP -->
            <AppTable :is-empty="rows.length === 0" class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 z-10 w-12 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">#</th>
                        <th class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">Judul Promo</th>
                        <th class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">Potongan</th>
                        <th class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">Periode</th>
                        <th class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">Berlaku Untuk</th>
                        <th class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">Status</th>
                        <th class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(promo, i) in rows" :key="promo.id" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-5 py-3 text-sm text-gray-400">{{ i + 1 }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                                    <BadgePercent class="h-4 w-4" />
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ promo.title }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatValue(promo) }}</td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ formatDate(promo.starts_at) }} — {{ formatDate(promo.ends_at) }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1">
                                <AppBadge v-for="type in promo.item_types" :key="type" color="gray" size="sm">
                                    {{ typeLabels[type] ?? type }}
                                </AppBadge>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <AppBadge :color="promo.is_running ? 'green' : (promo.is_active ? 'yellow' : 'gray')" dot>
                                {{ promo.is_running ? 'Berjalan' : (promo.is_active ? 'Terjadwal' : 'Nonaktif') }}
                            </AppBadge>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                <AppButton v-if="can('edit promo')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(promo)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('delete promo')" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(promo)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <template #pagination>
                    <AppPagination :paginator="promos" />
                </template>
            </AppTable>

            <!-- MOBILE -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                        <BadgePercent class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada promo.</p>
                </div>

                <div
                    v-for="promo in rows"
                    :key="promo.id"
                    class="rounded-xl border border-gray-200 bg-white px-3.5 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    @click="can('edit promo') && openEdit(promo)"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                            <BadgePercent class="h-3.5 w-3.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ promo.title }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ formatValue(promo) }} · {{ formatDate(promo.starts_at) }} — {{ formatDate(promo.ends_at) }}
                            </p>
                        </div>
                        <AppBadge :color="promo.is_running ? 'green' : (promo.is_active ? 'yellow' : 'gray')" size="sm" dot>
                            {{ promo.is_running ? 'Berjalan' : (promo.is_active ? 'Terjadwal' : 'Nonaktif') }}
                        </AppBadge>
                    </div>
                </div>
            </div>

            <div v-if="promos.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="promos" />
            </div>
        </div>
    </AppLayout>

    <PromoFormModal
        ref="modalRef"
        :show="showModal"
        :promo="selectedPromo"
        :item-type-options="itemTypeOptions"
        @close="closeModal"
        @submit="handleSubmit"
    />
</template>
