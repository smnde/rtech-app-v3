<script setup>
import { computed } from 'vue';
import { ArrowDownAZ, ArrowUpZA } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: String, default: 'asc' },
});

const emit = defineEmits(['update:modelValue']);

const isAsc = computed(() => props.modelValue === 'asc');

const toggle = () => emit('update:modelValue', isAsc.value ? 'desc' : 'asc');
</script>

<template>
    <button
        type="button"
        @click="toggle"
        class="inline-flex w-full items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:border-gray-400 dark:hover:border-gray-600 transition"
    >
        <ArrowDownAZ v-if="isAsc" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
        <ArrowUpZA v-else class="h-4 w-4 text-primary-600 dark:text-primary-400" />
        SKU {{ isAsc ? 'A-Z' : 'Z-A' }}
    </button>
</template>
