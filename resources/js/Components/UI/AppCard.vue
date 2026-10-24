<script setup>
defineProps({
    /** Tambah padding atau tidak */
    padded: {
        type: Boolean,
        default: true,
    },
    /** Class tambahan untuk wrapper di dalam card */
    bodyClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
        <div :class="[padded ? 'p-5 sm:p-6' : '', bodyClass]">
            <slot />
        </div>
    </div>
</template>
