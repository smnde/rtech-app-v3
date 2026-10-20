<script setup>
import { usePermission } from '@/composables/usePermission';
import { ref, computed, reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, Phone, UserCheck, X, MessageCircle, Eye } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import AppActionDrawer from '@/Components/UI/AppActionDrawer.vue';
import CustomerFormModal from '@/Components/Customers/CustomerFormModal.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { waLink } from '@/lib/whatsapp';

const { can } = usePermission();

const props = defineProps({
    customers: { type: Object, required: true },
    filters:   { type: Object, default: () => ({}) },
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

const rows = computed(() => props.customers.data);

const memberOptions = [
    { id: '', label: 'Semua customer' },
    { id: '1', label: 'Member' },
    { id: '0', label: 'Bukan member' },
];

const filterForm = reactive({
    search: props.filters.search ?? '',
    member: props.filters.member ?? '',
});

const hasActiveFilter = computed(() => filterForm.member !== '');

const applyFilter = () => {
    router.get(route('customers.index'), {
        search: filterForm.search || undefined,
        member: filterForm.member || undefined,
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

watch(() => filterForm.member, applyFilter);

const resetFilter = () => {
    filterForm.member = '';
};

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
        router.put(route('customers.update', selectedItem.value.id), payload, options);
    } else {
        router.post(route('customers.store'), payload, options);
    }
};

const handleDelete = async (item) => {
    const ok = await confirm.confirm({
        title: 'Hapus customer?',
        message: `Customer "${item.name}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('customers.destroy', item.id), { preserveScroll: true });
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
            router.visit(route('customers.show', item.id));
        },
    });

    // Chat WhatsApp
    if (waLink(item.phone)) {
        actions.push({
            label: 'Chat WhatsApp',
            icon: MessageCircle,
            onClick: () => {
                closeActionDrawer();
                window.open(waLink(item.phone), '_blank');
            },
        });
    }

    // Divider
    actions.push({ divider: true });

    // Edit
    if (can('edit customer')) {
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
    if (can('delete customer')) {
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
    <Head title="Customer" />

    <AppLayout title="Customer">

        <template v-if="can('add customer')" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah Customer" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <!-- App shell: header (search/tombol) diam, hanya list yang scroll -->
        <div class="flex h-full flex-col">

            <AppPageHeader title="Customer" description="Data customer yang terdaftar di sistem." class="shrink-0">
                <template #search>
                    <div class="flex w-full items-center gap-2">
                        <div class="relative flex-1 sm:flex-none">
                            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="filterForm.search"
                                type="text"
                                placeholder="Cari nama atau no. HP..."
                                class="w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>
                        <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status Member</label>
                                <AppDropdown v-model="filterForm.member" :options="memberOptions" placeholder="Semua customer" />
                            </div>
                        </AppFilterModal>
                    </div>
                </template>

                <template #actions>
                    <AppButton v-if="can('add customer')" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Customer
                    </AppButton>
                </template>
            </AppPageHeader>

            <div class="hidden shrink-0 mb-4 sm:flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-56">
                    <AppDropdown v-model="filterForm.member" :options="memberOptions" placeholder="Semua customer" />
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
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Nama Customer</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">No. HP</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Alamat</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Member</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(item, i) in rows" :key="item.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3 text-sm text-gray-400">{{ i + 1 }}</td>
                        <td class="px-5 py-3">
                            <Link :href="route('customers.show', item.id)" class="font-medium text-gray-900 dark:text-white text-sm hover:text-primary-600 dark:hover:text-primary-400">{{ item.name }}</Link>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-300">
                                <Phone class="h-3.5 w-3.5 text-gray-400" />
                                {{ item.phone }}
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ item.address ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="item.is_member ? 'purple' : 'gray'" size="sm">
                                {{ item.is_member ? 'Member' : 'Non-member' }}
                            </AppBadge>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1">
                                <a v-if="waLink(item.phone)"
                                    :href="waLink(item.phone)"
                                    target="_blank" rel="noopener"
                                    title="Chat WhatsApp"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-green-600 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-green-400">
                                    <MessageCircle class="h-4 w-4" />
                                </a>
                                <AppButton v-if="can('edit customer')" variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="can('delete customer')" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(item)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="customers" />
            </template>

            </AppTable>

            <!-- MOBILE VIEW: Card list — hanya list ini yang scroll -->
            <div class="scrollbar-styled scrollbar-mobile-hidden sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <UserCheck class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada customer ditemukan.</p>
                </div>

                <div
                    v-for="item in rows"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3.5 py-2.5 shadow-sm cursor-pointer active:bg-gray-50 dark:active:bg-gray-800/50"
                    @click="openActionDrawer(item)"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ item.phone }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <AppBadge :color="item.is_member ? 'purple' : 'gray'" size="sm">
                            {{ item.is_member ? 'Member' : 'Non-member' }}
                        </AppBadge>
                    </div>
                </div>
            </div>

            <div v-if="customers.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="customers" />
            </div>
        </div>

    </AppLayout>

    <CustomerFormModal
        ref="modalRef"
        :show="showModal"
        :item="selectedItem"
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
</template>
