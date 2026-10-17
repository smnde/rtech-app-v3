<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { KeyRound, LogIn, User } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: '' },
});

const page = usePage();
const toast = useToast();
const dark = computed(() => document.documentElement.classList.contains('dark'));
const store = computed(() => page.props.store ?? {});

const username = ref('');
const password = ref('');
const remember = ref(false);
const processing = ref(false);

// Tampilkan toast bila ada `status` dari server (misal 2FA / verifikasi email).
watch(
    () => page.props.status,
    (status) => {
        if (status) toast.info(status);
    },
    { immediate: true },
);

const submit = () => {
    if (!username.value || !password.value || processing.value) return;
    processing.value = true;
    router.post(
        route('login'),
        { username: username.value, password: password.value, remember: remember.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                password.value = '';
            },
            onError: (errors) => {
                toast.error(Object.values(errors)[0] ?? 'Username atau password salah.');
            },
        },
    );
};

const error = ref('');
</script>

<template>
    <Head title="Masuk — RTech App" />

    <div class="relative min-h-screen overflow-hidden bg-gray-950 text-white">
        <!-- Latar -->
        <div
            aria-hidden
            class="pointer-events-none absolute inset-0"
            :style="{ background: 'radial-gradient(70% 50% at 50% 0%, rgba(79,70,229,0.28), transparent 70%)' }"
        ></div>
        <div
            aria-hidden
            class="pointer-events-none absolute inset-0 opacity-[0.05] [background-image:linear-gradient(rgba(255,255,255,0.4)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.4)_1px,transparent_1px)] [background-size:44px_44px]"
        ></div>

        <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-md flex-col items-center justify-center px-4 py-12">
            <!-- Branding -->
            <div class="mb-9 flex flex-col items-center text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/15 ring-1 ring-indigo-400/30">
                    <img src="/icons/icon-192.png" alt="RTech App" class="h-10 w-10 rounded-xl object-contain" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">{{ store.name ?? 'RTech App' }}</h1>
                <p class="mt-1.5 text-sm text-gray-400">Masuk untuk mengelola kasir &amp; stok toko</p>
            </div>

            <!-- Kartu -->
            <div class="w-full rounded-2xl bg-white/[0.06] p-7 ring-1 ring-white/10 backdrop-blur-sm sm:p-8">
                <form @submit.prevent="submit">
                    <label class="block text-sm font-medium text-gray-300">
                        Username
                        <div class="relative mt-2">
                            <User
                                class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                            <input
                                v-model="username"
                                type="text"
                                required
                                autocomplete="username"
                                placeholder="mis. budi"
                                class="w-full rounded-lg border border-white/10 bg-white/[0.04] py-2.5 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-gray-500 focus:border-indigo-400 focus:bg-white/[0.08] focus:ring-2 focus:ring-indigo-400/40"
                            />
                        </div>
                    </label>

                    <label class="mt-5 block text-sm font-medium text-gray-300">
                        Password
                        <div class="relative mt-2">
                            <KeyRound
                                class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                            <input
                                v-model="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full rounded-lg border border-white/10 bg-white/[0.04] py-2.5 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-gray-500 focus:border-indigo-400 focus:bg-white/[0.08] focus:ring-2 focus:ring-indigo-400/40"
                            />
                        </div>
                    </label>

                    <!-- Pesan error dari server -->
                    <div v-if="error || processing && error" class="mt-4 rounded-lg bg-red-500/10 px-3.5 py-2.5 text-sm text-red-300 ring-1 ring-red-500/30">
                        {{ error }}
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-400">
                            <input
                                v-model="remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-white/20 bg-white/5 text-indigo-500 focus:ring-indigo-400/40"
                            />
                            Ingat saya
                        </label>

                        <router-link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-indigo-300 transition hover:text-indigo-200"
                        >
                            Lupa password?
                        </router-link>
                    </div>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="mt-7 flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <LogIn v-if="!processing" class="h-4 w-4" />
                        <span v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />
                        {{ processing ? 'Memproses…' : 'Masuk' }}
                    </button>
                </form>
            </div>

            <p class="mt-8 text-xs text-gray-500">
                &copy; {{ new Date().getFullYear() }} {{ store.name ?? 'RTech App' }}
            </p>
        </div>
    </div>
</template>
