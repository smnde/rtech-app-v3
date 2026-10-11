<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { mobileBottomNavItems } from '@/config/menu';

const page = usePage();
const visibleNavItems = mobileBottomNavItems.filter(
    (item) => !item.permission || (page.props.auth?.permissions ?? []).includes(item.permission),
);

const isActive = (routeName) => {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
};
</script>

<template>
    <!-- Bottom nav — hanya tampil di mobile (lg:hidden) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 safe-bottom">
        <div class="flex items-center justify-around h-16">
            <Link
                v-for="item in visibleNavItems"
                :key="item.key"
                :href="route(item.route)"
                class="flex flex-col items-center justify-center gap-1 flex-1 h-full px-1 transition-colors"
                :class="
                    isActive(item.route)
                        ? 'text-primary-600 dark:text-primary-400'
                        : 'text-gray-500 dark:text-gray-400'
                "
            >
                <component
                    :is="item.icon"
                    class="h-5 w-5"
                    :stroke-width="isActive(item.route) ? 2.5 : 1.8"
                />
                <span class="text-[10px] font-medium leading-none">{{ item.label }}</span>
                <!-- Indikator aktif -->
                <span
                    v-if="isActive(item.route)"
                    class="absolute bottom-0 h-0.5 w-8 rounded-full bg-primary-600 dark:bg-primary-400"
                ></span>
            </Link>
        </div>
    </nav>
</template>
