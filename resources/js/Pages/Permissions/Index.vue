<script setup>
import { ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, KeyRound } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    modules: { type: Array, required: true },
    actions: { type: Array, required: true },
});

const toast = useToast();
const page  = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const actionLabels = { show: 'Lihat', add: 'Tambah', edit: 'Edit', delete: 'Hapus' };

const showModal = ref(false);
const form = ref({ module: '' });
const errors = ref({});
const submitting = ref(false);

const openCreate = () => {
    form.value = { module: '' };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };

const submit = () => {
    submitting.value = true;
    router.post(route('permissions.store'), { module: form.value.module.trim().toLowerCase() }, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (errs) => { errors.value = errs; },
        onFinish: () => { submitting.value = false; },
    });
};
</script>

<template>
    <Head title="Permission" />

    <AppLayout title="Permission">
        <div class="flex h-full flex-col">
            <AppPageHeader title="Permission" description="Modul hak akses. Tiap modul otomatis punya aksi Lihat, Tambah, Edit, dan Hapus." class="shrink-0">
                <template #actions>
                    <AppButton variant="primary" size="md" class="hidden w-full sm:flex sm:w-auto" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Tambah Permission
                    </AppButton>
                </template>
            </AppPageHeader>

            <AppTable :is-empty="modules.length === 0" class="flex-1 min-h-0">
                <thead>
                    <tr>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Modul</th>
                        <th class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800 px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="module in modules" :key="module" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <KeyRound class="h-4 w-4 text-gray-400" />
                                <span class="font-mono text-sm text-gray-900 dark:text-white">{{ module }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ actions.map((action) => actionLabels[action] ?? action).join(', ') }}
                        </td>
                    </tr>
                </tbody>
            </AppTable>
        </div>
    </AppLayout>

    <AppModal :show="showModal" title="Tambah Permission" size="sm" @close="closeModal">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Nama Modul <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.module"
                    type="text"
                    placeholder="Contoh: report_export"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm font-mono text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
                <p class="mt-1.5 text-xs text-gray-400">Huruf kecil, angka, dan underscore. Akan dibuat 4 permission: show, add, edit, delete.</p>
                <p v-if="errors.module" class="mt-1.5 text-xs text-red-500">{{ errors.module }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <AppButton variant="ghost" size="md" @click="closeModal">Batal</AppButton>
                <AppButton variant="primary" size="md" type="submit" :loading="submitting">Simpan</AppButton>
            </div>
        </form>
    </AppModal>
</template>
