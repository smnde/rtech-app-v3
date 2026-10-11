<script setup>
import { ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { X, ChevronDown, LogOut } from 'lucide-vue-next';
import { menuItems as allMenuItems, filterMenuByPermission } from '@/config/menu';

const page = usePage();
const menuItems = filterMenuByPermission(
    allMenuItems,
    page.props.auth?.permissions ?? [],
    page.props.auth?.roles ?? [],
);

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const openMenus = ref({});

const toggleMenu = (key) => {
    openMenus.value[key] = !openMenus.value[key];
};

const isActive = (routeName) => {
    try { return route().current(routeName); } catch { return false; }
};

const isParentActive = (item) => {
    if (!item.children) return isActive(item.route);
    return item.children.some((child) => isActive(child.route));
};

// Auto-buka submenu aktif
menuItems.forEach((item) => {
    if (item.children && item.children.some((child) => isActive(child.route))) {
        openMenus.value[item.key] = true;
    }
});

// Tutup drawer saat navigasi (route berubah)
watch(() => page.url, () => emit('close'));
</script>

<template>
    <Teleport to="body">
        <!-- Overlay backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="lg:hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm"
                @click="$emit('close')"
            />
        </Transition>

        <!-- Drawer panel -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <aside
                v-if="open"
                class="lg:hidden fixed inset-y-0 left-0 z-50 w-72 flex flex-col bg-white dark:bg-gray-900 shadow-xl"
            >
                <!-- Header drawer -->
                <div class="flex h-16 items-center justify-between px-5 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white font-bold text-sm">
                            RT
                        </div>
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">RTech App</span>
                    </div>
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="scrollbar-styled flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
                    <template v-for="item in menuItems" :key="item.key">

                        <Link
                            v-if="!item.children"
                            :href="route(item.route)"
                            :class="[
                                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                                isActive(item.route)
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800',
                            ]"
                        >
                            <component :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span>{{ item.label }}</span>
                        </Link>

                        <div v-else>
                            <button
                                type="button"
                                @click="toggleMenu(item.key)"
                                :class="[
                                    'flex w-full items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                                    isParentActive(item)
                                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800',
                                ]"
                            >
                                <component :is="item.icon" class="h-5 w-5 shrink-0" />
                                <span class="flex-1 text-left">{{ item.label }}</span>
                                <ChevronDown
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{ 'rotate-180': openMenus[item.key] }"
                                />
                            </button>

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
                                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800',
                                    ]"
                                >
                                    <component :is="child.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ child.label }}</span>
                                </Link>
                            </div>
                        </div>

                    </template>
                </nav>

                <!-- User + logout -->
                <div class="border-t border-gray-200 dark:border-gray-800 p-3">
                    <div class="flex items-center gap-3 rounded-lg px-3 py-2">
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
                        >
                            <LogOut class="h-4 w-4" />
                        </Link>
                    </div>
                </div>

            </aside>
        </Transition>
    </Teleport>
</template>
