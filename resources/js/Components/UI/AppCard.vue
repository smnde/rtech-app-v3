<script setup>
defineProps({
    /** Tambah padding atau tidak */
    padded: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
        <div :class="padded ? 'p-5 sm:p-6' : ''">
            <slot />
        </div>
    </div>
</template>
