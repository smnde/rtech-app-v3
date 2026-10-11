<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <!-- Judul disembunyikan di mobile (sudah tampil di navbar) -->
        <div class="hidden sm:block">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
        </div>

        <!-- Sisi kanan: search (opsional) sejajar dengan actions -->
        <!-- Ada search: mobile bertumpuk (full width), sm+ sejajar satu baris. Tanpa search: seperti semula. -->
        <div
            v-if="$slots.search || $slots.actions"
            class="flex gap-2"
            :class="$slots.search
                ? 'w-full flex-col sm:w-auto sm:flex-row sm:items-center'
                : 'flex-shrink-0 items-center'"
        >
            <div v-if="$slots.search" class="min-w-0 sm:flex-none">
                <slot name="search" />
            </div>
            <div v-if="$slots.actions" class="flex flex-shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>