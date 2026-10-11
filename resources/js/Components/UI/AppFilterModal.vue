<script setup>
import { ref, provide } from 'vue';
import { SlidersHorizontal, X } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';

defineProps({
    active: { type: Boolean, default: false },
});

defineEmits(['reset']);

const open = ref(false);
provide('dropdownInline', true);
</script>

<template>
    <button
        type="button"
        class="relative flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors sm:hidden"
        :class="active
            ? 'border-primary-500 bg-primary-600 text-white'
            : 'border-gray-300 bg-white text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'"
        @click="open = true"
    >
        <SlidersHorizontal class="h-4 w-4" />
        Filter
        <span v-if="active" class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400" />
    </button>

    <AppModal :show="open" title="Filter & Sort" size="sm" @close="open = false">
        <div class="space-y-4">
            <slot />
        </div>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('reset')">
                <X class="h-4 w-4" />
                Reset
            </AppButton>
            <AppButton variant="primary" size="sm" @click="open = false">Terapkan</AppButton>
        </template>
    </AppModal>
</template>
