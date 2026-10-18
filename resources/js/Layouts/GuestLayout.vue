<script setup>
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const store = page.props.store ?? {};
const isDark = document.documentElement.classList.contains('dark');
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gray-100 px-4 py-10 dark:bg-gray-950"
    >
        <!-- Latar dekoratif -->
        <div
            aria-hidden
            class="pointer-events-none absolute inset-x-0 -top-1/3 h-[420px]"
            :style="{
                background: 'radial-gradient(60% 60% at 50% 0%, rgba(79,70,229,0.18), transparent)',
            }"
        ></div>

        <!-- Branding -->
        <Link
            :to="route('dashboard')"
            class="relative z-10 mb-8 flex flex-col items-center gap-3 text-center"
        >
            <div
                :class="[
                    'flex h-14 w-14 items-center justify-center rounded-2xl shadow-lg',
                    isDark ? 'bg-indigo-500/20 ring-1 ring-indigo-500/30' : 'bg-white ring-1 ring-indigo-500/10',
                ]"
            >
                <img src="/icons/icon-512.png" alt="RTech App" class="h-12 w-12 rounded-xl" />
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">
                    {{ store.name ?? 'RTech App' }}
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Sistem Kasir & Stok</p>
            </div>
        </Link>

        <!-- Panel -->
        <div
            :class="[
                'relative z-10 w-full overflow-hidden rounded-2xl shadow-xl ring-1 sm:max-w-md',
                isDark
                    ? 'bg-gray-900 ring-white/10'
                    : 'bg-white ring-gray-900/5',
            ]"
        >
            <div class="px-6 py-7 sm:px-8">
                <slot />
            </div>
        </div>

        <!-- Versi -->
        <p class="relative z-10 mt-8 text-[11px] text-gray-400 dark:text-gray-600">
            &copy; {{ new Date().getFullYear() }} {{ store.name ?? 'RTech App' }}
        </p>
    </div>
</template>
