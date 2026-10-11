<script setup>
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue';
import { ChevronDown, Check } from 'lucide-vue-next';

/**
 * AppDropdown — dropdown tanpa search. Opsi berupa string atau { id, label }.
 */
const props = defineProps({
    modelValue:  { type: [String, Number], default: '' },
    options:     { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih...' },
    error:       { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const isInline = inject('dropdownInline', false);

const open = ref(false);
const rootRef = ref(null);

const normalizedOptions = computed(() =>
    props.options.map((o) =>
        typeof o === 'object' && o !== null
            ? { value: o.id, label: o.label }
            : { value: o, label: String(o) },
    ),
);

const selectedLabel = computed(() => {
    const match = normalizedOptions.value.find((o) => o.value === props.modelValue);
    return match ? match.label : props.placeholder;
});

const hasValue = computed(() => normalizedOptions.value.some((o) => o.value === props.modelValue));

const toggle = () => {
    if (props.disabled) return;
    open.value = !open.value;
};

const choose = (option) => {
    emit('update:modelValue', option.value);
    open.value = false;
};

const onDocumentMousedown = (e) => {
    if (!rootRef.value?.contains(e.target)) open.value = false;
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && open.value) {
        e.stopPropagation();
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', onDocumentMousedown);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentMousedown);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="toggle"
            :class="[
                'flex w-full items-center justify-between gap-2 rounded-lg border bg-white dark:bg-gray-800 px-3.5 py-2.5 text-left text-sm transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60',
                error
                    ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                    : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600 focus:ring-primary-500',
                hasValue ? 'text-gray-900 dark:text-white' : 'text-gray-400',
            ]"
        >
            <span class="truncate">{{ selectedLabel }}</span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200', open ? 'rotate-180' : '']" />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <ul
                v-if="open"
                role="listbox"
                :class="[isInline ? 'relative mt-1.5' : 'absolute left-0 right-0 z-[60] mt-1.5', 'max-h-60 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 shadow-xl scrollbar-styled']"
            >
                <li
                    v-for="option in normalizedOptions"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === modelValue"
                    @click="choose(option)"
                    :class="[
                        'flex cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm transition-colors',
                        option.value === modelValue
                            ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/70',
                    ]"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <Check v-if="option.value === modelValue" class="h-4 w-4 shrink-0" />
                </li>
            </ul>
        </Transition>
    </div>
</template>
