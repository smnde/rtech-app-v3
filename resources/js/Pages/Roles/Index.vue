<script setup>
import { ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Shield, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    roles: { type: Array, required: true },
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

const showModal = ref(false);
const form = ref({ name: '' });
const errors = ref({});
const submitting = ref(false);

const openCreate = () => {
    form.value = { name: '' };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submit = () => {
    submitting.value = true;
    router.post(route('roles.store'), { name: form.value.name.trim().toUpperCase() }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; },
        onFinish: () => { submitting.value = false; },
    });
};

const handleDelete = async (role) => {
    const ok = await confirm.confirm({
        title: 'Hapus role?',
        message: `Role "${role.name}" akan dihapus permanen.`,
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('roles.destroy', role.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Role" />

    <AppLayout title="Role">
        <div class="flex h-full flex-col">
            <AppPageHeader title="Role" description="Daftar role yang tersedia di sistem." class="shrink-0">
                <template #actions>
                    <AppButton variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Role
                    </AppButton>
                </template>
            </AppPageHeader>

            <AppTable :is-empty="roles.length === 0" class="flex-1 min-h-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Nama Role</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Jumlah User</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <Shield class="h-4 w-4 text-gray-400" />
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ role.name }}</span>
                                <AppBadge v-if="role.is_built_in" color="gray" size="sm">Bawaan</AppBadge>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ role.users_count }}</td>
                        <td class="px-5 py-3">
                            <AppButton
                                v-if="!role.is_built_in"
                                variant="ghost"
                                size="sm"
                                icon-only
                                title="Hapus"
                                class="hover:!text-red-500 dark:hover:!text-red-400"
                                @click="handleDelete(role)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </AppButton>
                        </td>
                    </tr>
                </tbody>
            </AppTable>
        </div>
    </AppLayout>

    <AppModal :show="showModal" title="Tambah Role" size="sm" @close="closeModal">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Nama Role <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="Contoh: KASIR"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm uppercase text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
                <p class="mt-1.5 text-xs text-gray-400">Huruf kapital, angka, dan underscore. Contoh: KASIR, GUDANG_2.</p>
                <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <AppButton variant="ghost" size="md" @click="closeModal">Batal</AppButton>
                <AppButton variant="primary" size="md" type="submit" :loading="submitting">Simpan</AppButton>
            </div>
        </form>
    </AppModal>
</template>
