<script setup>
/**
 * AppTable — wrapper tabel yang sudah di-style dengan dark mode.
 * Gunakan slot default untuk mengisi thead dan tbody.
 */
defineProps({
    /** Tampilkan state loading */
    loading: { type: Boolean, default: false },
    /** Pesan saat data kosong */
    emptyMessage: { type: String, default: 'Tidak ada data.' },
    /** Sembunyikan tabel dan tampilkan empty state */
    isEmpty: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex flex-col rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <!-- Search/filter slot atas tabel -->
        <div v-if="$slots.toolbar" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-800">
            <slot name="toolbar" />
        </div>

        <!-- flex-1/min-h-0 baru aktif saat dipakai di layout setinggi penuh (lihat Users/Index) -->
        <div class="scrollbar-styled flex-1 min-h-0 overflow-auto">
            <!-- Loading state -->
            <div v-if="loading" class="flex items-center justify-center py-16">
                <svg class="animate-spin h-6 w-6 text-primary-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Memuat data...</span>
            </div>

            <!-- Empty state -->
            <div v-else-if="isEmpty" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-3 rounded-full bg-gray-100 dark:bg-gray-800 p-4">
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ emptyMessage }}</p>
            </div>

            <!-- Table -->
            <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <slot />
            </table>
        </div>

        <!-- Pagination slot -->
        <div v-if="$slots.pagination" class="border-t border-gray-200 dark:border-gray-800 px-5 py-3">
            <slot name="pagination" />
        </div>
    </div>
</template>
