<script setup>
import { watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { User, Lock, AtSign } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { useProfileModal } from '@/composables/useProfileModal';

const page = usePage();
const profileModal = useProfileModal();

const form = useForm({
    name: '',
    username: '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const inputClass = (hasError) => [
    'w-full rounded-lg border bg-white dark:bg-gray-800 py-2.5 pl-9 pr-3.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError
        ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
        : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];

watch(
    () => profileModal.show.value && page.props.auth?.user,
    (active) => {
        if (!active) return;
        const user = page.props.auth.user;
        form.reset();
        form.clearErrors();
        form.name = user.name ?? '';
        form.username = user.username ?? '';
    },
);

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => profileModal.close(),
    });
};
</script>

<template>
    <AppModal :show="profileModal.show.value" title="Profil Saya" size="md" @close="profileModal.close()">
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                <div class="relative">
                    <User class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input v-model="form.name" type="text" placeholder="Nama lengkap" :class="inputClass(form.errors.name)" />
                </div>
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-500">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                <div class="relative">
                    <AtSign class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input v-model="form.username" type="text" placeholder="Username" :class="inputClass(form.errors.username)" />
                </div>
                <p v-if="form.errors.username" class="mt-1.5 text-xs text-red-500">{{ form.errors.username }}</p>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-800 pt-4">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Ganti Password</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Kosongkan jika tidak ingin mengganti password.</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password saat ini</label>
                <div class="relative">
                    <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input v-model="form.current_password" type="password" autocomplete="current-password" placeholder="Password saat ini" :class="inputClass(form.errors.current_password)" />
                </div>
                <p v-if="form.errors.current_password" class="mt-1.5 text-xs text-red-500">{{ form.errors.current_password }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password baru</label>
                    <div class="relative">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input v-model="form.password" type="password" autocomplete="new-password" placeholder="Password baru" :class="inputClass(form.errors.password)" />
                    </div>
                    <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-500">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi</label>
                    <div class="relative">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input v-model="form.password_confirmation" type="password" autocomplete="new-password" placeholder="Ulangi password baru" :class="inputClass(false)" />
                    </div>
                </div>
            </div>
        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="profileModal.close()">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="form.processing" @click="submit">Simpan</AppButton>
        </template>
    </AppModal>
</template>
