<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Ketik lalu Enter' },
    error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');

const addTag = () => {
    const value = draft.value.trim();
    if (!value) return;
    emit('update:modelValue', [...props.modelValue, value]);
    draft.value = '';
};

const removeTag = (index) => {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
};

const onBackspace = () => {
    if (draft.value !== '' || props.modelValue.length === 0) return;
    removeTag(props.modelValue.length - 1);
};
</script>

<template>
    <div :class="[
        'flex flex-wrap items-center gap-1.5 min-h-[42px] w-full rounded-lg border px-2.5 py-1.5 bg-white dark:bg-gray-800 transition focus-within:ring-2',
        error
            ? 'border-red-400 dark:border-red-500 focus-within:ring-red-400'
            : 'border-gray-300 dark:border-gray-700 focus-within:ring-primary-500',
    ]">
        <span
            v-for="(tag, index) in modelValue"
            :key="`${tag}-${index}`"
            class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300"
        >
            {{ tag }}
            <button
                type="button"
                class="leading-none text-primary-500 hover:text-primary-800 dark:hover:text-white"
                :aria-label="`Hapus ${tag}`"
                @click="removeTag(index)"
            >&times;</button>
        </span>
        <input
            v-model="draft"
            type="text"
            :placeholder="modelValue.length === 0 ? placeholder : ''"
            class="min-w-[120px] flex-1 appearance-none border-0 bg-transparent px-1 py-0.5 text-sm text-gray-900 placeholder:text-gray-400 outline-none ring-0 focus:border-0 focus:outline-none focus:ring-0 focus-visible:outline-none dark:text-white"
            @keydown.enter.prevent="addTag"
            @keydown.backspace="onBackspace"
        />
    </div>
</template>
