<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Pencil, Trash2, Shield } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import UserFormModal from '@/Components/Users/UserFormModal.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    users:         { type: Object, required: true },
    filters:       { type: Object, default: () => ({}) },
    canCreate:     { type: Boolean, default: false },
    roles:         { type: Array, default: () => [] },
});

const toast   = useToast();
const confirm = useConfirm();
const page    = usePage();

// ─── Flash dari server → toast ────────────────────────────────
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

// ─── Data dari server ─────────────────────────────────────────
const rows = computed(() => props.users.data);

// ─── Search (server-side) ─────────────────────────────────────
const search = ref(props.filters.search ?? '');
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('users.index'), { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

// ─── Modal state ──────────────────────────────────────────────
const showModal    = ref(false);
const selectedUser = ref(null);  // null = create, object = edit
const modalRef     = ref(null);

const openCreate = () => { selectedUser.value = null; showModal.value = true; };
const openEdit   = (user) => { selectedUser.value = { ...user }; showModal.value = true; };
const closeModal = () => { showModal.value = false; selectedUser.value = null; };

const handleSubmit = (payload) => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError:   (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish:  () => modalRef.value?.stopLoading(),
    };

    if (selectedUser.value) {
        router.put(route('users.update', selectedUser.value.id), payload, options);
    } else {
        router.post(route('users.store'), payload, options);
    }
};

const handleDelete = async (user) => {
    const ok = await confirm.confirm({
        title: 'Hapus user?',
        message: `User "${user.name}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('users.destroy', user.id), { preserveScroll: true });
};

// ─── Helpers ──────────────────────────────────────────────────
const roleColor = { OWNER: 'purple', SYSADMIN: 'red', ADMIN: 'blue', TECH: 'orange' };
const roleLabel = { OWNER: 'Owner', SYSADMIN: 'Sysadmin', ADMIN: 'Admin', TECH: 'Teknisi' };

const getInitials = (name) =>
    name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();

const columns = [
    { key: 'name',       label: 'Nama' },
    { key: 'username',   label: 'Username' },
    { key: 'role',       label: 'Role' },
    { key: 'created_at', label: 'Dibuat' },
    { key: 'action',     label: 'Aksi' },
];
</script>

<template>
    <Head title="User" />

    <AppLayout title="User">

        <template v-if="canCreate" #fab>
            <AppButton variant="primary" size="md" class="!h-14 !w-14 !rounded-full !p-0 shadow-lg" title="Tambah User" @click="openCreate">
                <Plus class="h-6 w-6" />
            </AppButton>
        </template>

        <!-- App shell: header (judul/search/tombol) diam, hanya daftar user yang scroll -->
        <div class="flex h-full flex-col">

            <AppPageHeader title="User" description="Kelola akun dan hak akses pengguna." class="shrink-0">
                <template #search>
                    <div class="relative w-full sm:w-auto">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari nama atau username..."
                            class="w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                </template>

                <template #actions>
                    <AppButton v-if="canCreate" variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah User
                    </AppButton>
                </template>
            </AppPageHeader>

            <!-- =====================================================
                 DESKTOP VIEW (sm+): Tabel — hanya isi tabel yang scroll
                 ===================================================== -->
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
                    <tr v-for="user in rows" :key="user.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <!-- Nama + avatar -->
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 text-xs font-semibold">
                                    {{ getInitials(user.name) }}
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ user.name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ user.username }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="roleColor[user.role] ?? 'gray'" size="sm">
                                <Shield class="h-3 w-3" />
                                {{ roleLabel[user.role] ?? user.role }}
                            </AppBadge>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ user.created_at }}</td>
                        <td class="px-5 py-3">
                            <div v-if="user.can_edit" class="flex items-center gap-1">
                                <AppButton variant="ghost" size="sm" icon-only title="Edit" @click="openEdit(user)">
                                    <Pencil class="h-4 w-4" />
                                </AppButton>
                                <AppButton v-if="user.can_delete" variant="ghost" size="sm" icon-only title="Hapus"
                                    class="hover:!text-red-500 dark:hover:!text-red-400"
                                    @click="handleDelete(user)">
                                    <Trash2 class="h-4 w-4" />
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            <template #pagination>
                <AppPagination :paginator="users" />
            </template>

            </AppTable>

            <!-- =====================================================
                 MOBILE VIEW: Card list — hanya list ini yang scroll
                 ===================================================== -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-3">
                <!-- Empty state mobile -->
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                        <Shield class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada user ditemukan.</p>
                </div>

                <!-- User cards -->
                <div
                    v-for="user in rows"
                    :key="user.id"
                    class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <!-- Avatar -->
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 font-semibold">
                            {{ getInitials(user.name) }}
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ user.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">@{{ user.username }}</p>
                        </div>

                        <!-- Badge role -->
                        <AppBadge :color="roleColor[user.role] ?? 'gray'" size="sm">
                            {{ roleLabel[user.role] ?? user.role }}
                        </AppBadge>
                    </div>

                    <!-- Footer card: tanggal + aksi -->
                    <div class="mt-3 flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
                        <span class="text-xs text-gray-400">Dibuat {{ user.created_at }}</span>
                        <div v-if="user.can_edit" class="flex items-center gap-1">
                            <AppButton variant="ghost" size="sm" icon-only @click="openEdit(user)">
                                <Pencil class="h-4 w-4" />
                            </AppButton>
                            <AppButton v-if="user.can_delete" variant="ghost" size="sm" icon-only
                                class="hover:!text-red-500 dark:hover:!text-red-400"
                                @click="handleDelete(user)">
                                <Trash2 class="h-4 w-4" />
                            </AppButton>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="users.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="users" />
            </div>
        </div>

    </AppLayout>

    <!-- Modal -->
    <UserFormModal
        ref="modalRef"
        :show="showModal"
        :user="selectedUser"
        :roles="roles"
        @close="closeModal"
        @submit="handleSubmit"
    />
</template>
