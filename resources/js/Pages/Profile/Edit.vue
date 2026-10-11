<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppButton from '@/Components/UI/AppButton.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const nameForm = useForm({ name: props.profile.name });
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 transition',
    hasError
        ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
        : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];

const submitName = () => nameForm.patch(route('profile.update'), { preserveScroll: true });

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <Head title="Profil" />

    <AppLayout title="Profil">
        <div class="flex h-full flex-col">
            <AppPageHeader title="Profil" description="Ubah nama dan password akun Anda." class="shrink-0" />

            <div class="scrollbar-styled flex-1 min-h-0 overflow-auto space-y-6">
                <AppCard>
                    <form @submit.prevent="submitName" class="space-y-4 max-w-md">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Nama</h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Username</label>
                            <input :value="profile.username" type="text" disabled
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm bg-gray-100 dark:bg-gray-800 text-gray-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nama</label>
                            <input v-model="nameForm.name" type="text" :class="inputClass(nameForm.errors.name)" />
                            <p v-if="nameForm.errors.name" class="mt-1.5 text-xs text-red-500">{{ nameForm.errors.name }}</p>
                        </div>
                        <div class="flex justify-end">
                            <AppButton variant="primary" size="sm" type="submit" :loading="nameForm.processing">Simpan Nama</AppButton>
                        </div>
                    </form>
                </AppCard>

                <AppCard>
                    <form @submit.prevent="submitPassword" class="space-y-4 max-w-md">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Ganti Password</h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password saat ini</label>
                            <input v-model="passwordForm.current_password" type="password" :class="inputClass(passwordForm.errors.current_password)" />
                            <p v-if="passwordForm.errors.current_password" class="mt-1.5 text-xs text-red-500">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password baru</label>
                            <input v-model="passwordForm.password" type="password" :class="inputClass(passwordForm.errors.password)" />
                            <p v-if="passwordForm.errors.password" class="mt-1.5 text-xs text-red-500">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Konfirmasi password baru</label>
                            <input v-model="passwordForm.password_confirmation" type="password" :class="inputClass(false)" />
                        </div>
                        <div class="flex justify-end">
                            <AppButton variant="primary" size="sm" type="submit" :loading="passwordForm.processing">Ganti Password</AppButton>
                        </div>
                    </form>
                </AppCard>
            </div>
        </div>
    </AppLayout>
</template>
