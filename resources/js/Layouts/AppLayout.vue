<script setup>
import { ref } from 'vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import AppNavbar from '@/Components/AppNavbar.vue';
import MobileBottomNav from '@/Components/MobileBottomNav.vue';
import MobileSidebarDrawer from '@/Components/MobileSidebarDrawer.vue';
import AppToast from '@/Components/UI/AppToast.vue';
import AppConfirmModal from '@/Components/UI/AppConfirmModal.vue';
import AppFab from '@/Components/UI/AppFab.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
});

const mobileMenuOpen = ref(false);
</script>

<template>
    <div class="h-dvh overflow-hidden bg-gray-50 dark:bg-gray-950">

        <!-- =========================================
             DESKTOP LAYOUT (lg+): Sidebar kiri fixed
             ========================================= -->
        <div class="hidden lg:flex lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:z-30">
            <AppSidebar />
        </div>

        <!-- =========================================
             MOBILE: Drawer sidebar (overlay)
             ========================================= -->
        <MobileSidebarDrawer
            :open="mobileMenuOpen"
            @close="mobileMenuOpen = false"
        />

        <!-- =========================================
             MAIN CONTENT AREA
             ========================================= -->
        <div class="lg:pl-64 flex h-full flex-col">

            <!-- Top navbar -->
            <AppNavbar
                :title="title"
                @toggle-mobile-sidebar="mobileMenuOpen = true"
            />

            <!-- Page content -->
            <main class="scrollbar-styled flex-1 min-h-0 overflow-hidden flex flex-col px-4 py-6 sm:px-6">
                <slot />
            </main>

        </div>

        <!-- =========================================
             MOBILE: Bottom navigation bar
             ========================================= -->
        <!-- <MobileBottomNav /> -->

        <AppFab v-if="$slots.fab">
            <slot name="fab" />
        </AppFab>

        <AppToast />
        <AppConfirmModal />

    </div>
</template>
