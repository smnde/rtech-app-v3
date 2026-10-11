<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, LogOut } from 'lucide-vue-next';
import { menuItems as allMenuItems, filterMenuByPermission } from '@/config/menu';

const page = usePage();

const menuItems = filterMenuByPermission(
    allMenuItems,
    page.props.auth?.permissions ?? [],
    page.props.auth?.roles ?? [],
);

// Track submenu yang sedang terbuka
const openMenus = ref({});

const toggleMenu = (key) => {
    openMenus.value[key] = !openMenus.value[key];
};

/**
 * Cek apakah route tertentu sedang aktif.
 * Untuk parent menu, aktif jika salah satu children aktif.
 */
const isActive = (routeName) => {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
};

const isParentActive = (item) => {
    if (!item.children) return isActive(item.route);
    return item.children.some((child) => isActive(child.route));
};

// Auto-buka submenu yang sedang aktif saat mount
menuItems.forEach((item) => {
    if (item.children && item.children.some((child) => isActive(child.route))) {
        openMenus.value[item.key] = true;
    }
});
</script>

<template>
    <!-- Sidebar wrapper -->
    <aside class="flex h-full w-64 flex-col bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800">

        <!-- Logo / App name -->
        <div class="flex h-16 items-center gap-3 px-5 border-b border-gray-200 dark:border-gray-800">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white font-bold text-sm shadow">
                RT
            </div>
            <span class="text-lg font-semibold text-gray-900 dark:text-white tracking-tight">RTech App</span>
        </div>

        <!-- Navigation -->
        <nav class="scrollbar-styled flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
            <template v-for="item in menuItems" :key="item.key">

                <!-- Item tanpa children -->
                <Link
                    v-if="!item.children"
                    :href="route(item.route)"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.route)
                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100',
                    ]"
                >
                    <component :is="item.icon" class="h-5 w-5 shrink-0" />
                    <span>{{ item.label }}</span>
                </Link>

                <!-- Item dengan children (collapsible) -->
                <div v-else>
                    <button
                        type="button"
                        @click="toggleMenu(item.key)"
                        :class="[
                            'flex w-full items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                            isParentActive(item)
                                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100',
                        ]"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span class="flex-1 text-left">{{ item.label }}</span>
                        <ChevronDown
                            class="h-4 w-4 shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': openMenus[item.key] }"
                        />
                    </button>

                    <!-- Submenu items -->
                    <div
                        v-show="openMenus[item.key]"
                        class="mt-0.5 ml-4 pl-3 border-l border-gray-200 dark:border-gray-700 space-y-0.5"
                    >
                        <Link
                            v-for="child in item.children"
                            :key="child.key"
                            :href="route(child.route)"
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors',
                                isActive(child.route)
                                    ? 'text-primary-700 dark:text-primary-400 font-medium bg-primary-50 dark:bg-primary-900/20'
                                    : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100',
                            ]"
                        >
                            <component :is="child.icon" class="h-4 w-4 shrink-0" />
                            <span>{{ child.label }}</span>
                        </Link>
                    </div>
                </div>

            </template>
        </nav>

        <!-- User info + Logout -->
        <div class="border-t border-gray-200 dark:border-gray-800 p-3">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2">
                <!-- Avatar -->
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 font-semibold text-sm">
                    {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                        {{ page.props.auth?.user?.name ?? 'User' }}
                    </p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        @{{ page.props.auth?.user?.username ?? '' }}
                    </p>
                </div>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-lg p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    title="Logout"
                >
                    <LogOut class="h-4 w-4" />
                </Link>
            </div>
        </div>

    </aside>
</template>
