<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
});

const TYPES = {
    upgrade: { label: 'Upgrade', cls: 'text-indigo-600 dark:text-indigo-400 bg-indigo-500/10 border-indigo-500/20' },
    repair:  { label: 'Perbaikan', cls: 'text-amber-600 dark:text-amber-400 bg-amber-500/10 border-amber-500/20' },
    note:    { label: 'Catatan', cls: 'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700' },
};

const config = computed(() => TYPES[props.type] ?? TYPES.note);
</script>

<template>
    <span :class="config.cls" class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium">
        {{ config.label }}
    </span>
</template>
