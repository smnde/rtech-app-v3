<script setup>
import { Sun, Moon, Bell, Menu } from 'lucide-vue-next';
import { useDarkMode } from '@/composables/useDarkMode';

defineProps({
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['toggle-mobile-sidebar']);

const { isDark, toggle } = useDarkMode();
</script>

<template>
    <header class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 sm:px-6">

        <!-- Kiri: Hamburger (mobile) + Page title -->
        <div class="flex items-center gap-3">
            <!-- Hamburger untuk mobile (hidden di lg) -->
            <button
                type="button"
                class="lg:hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                @click="$emit('toggle-mobile-sidebar')"
            >
                <Menu class="h-5 w-5" />
            </button>

            <h1 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ title }}
            </h1>
        </div>

        <!-- Kanan: actions -->
        <div class="flex items-center gap-1">

            <!-- Notifikasi -->
            <button
                type="button"
                class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                title="Notifikasi"
            >
                <Bell class="h-5 w-5" />
                <!-- Badge notifikasi (contoh) -->
                <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500"></span>
            </button>

            <!-- Dark mode toggle -->
            <button
                type="button"
                @click="toggle"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            >
                <Sun v-if="isDark" class="h-5 w-5" />
                <Moon v-else class="h-5 w-5" />
            </button>

        </div>
    </header>
</template>
