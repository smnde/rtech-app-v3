<script setup>
import { computed } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';

/**
 * AppNumberInput — pengganti <input type="number">.
 * Spinner up/down native tidak bisa di-style konsisten antar browser,
 * jadi tombol stepper-nya dirender sendiri (ikut tema light/dark).
 */
const props = defineProps({
    modelValue:  { type: [String, Number], default: '' },
    min:         { type: Number, default: null },
    max:         { type: Number, default: null },
    step:        { type: Number, default: 1 },
    placeholder: { type: String, default: '0' },
    error:       { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const numericValue = computed(() => {
    const v = props.modelValue;
    if (v === '' || v === null || v === undefined) return null;

    const n = Number(v);
    return Number.isNaN(n) ? null : n;
});

const canStepUp = computed(
    () => props.max === null || (numericValue.value ?? (props.min ?? 0)) < props.max,
);
const canStepDown = computed(
    () => props.min === null || (numericValue.value ?? props.min) > props.min,
);

const clamp = (n) => {
    let value = n;
    if (props.min !== null) value = Math.max(props.min, value);
    if (props.max !== null) value = Math.min(props.max, value);
    return value;
};

const stepBy = (delta) => {
    const base = numericValue.value ?? (props.min ?? 0);
    emit('update:modelValue', clamp(base + delta));
};

const stepUp   = () => { if (canStepUp.value) stepBy(props.step); };
const stepDown = () => { if (canStepDown.value) stepBy(-props.step); };

// Hanya terima digit — karakter non-angka langsung dibuang
const onInput = (e) => {
    const digits = e.target.value.replace(/\D/g, '');
    e.target.value = digits === '' ? '' : String(Number(digits));
    emit('update:modelValue', digits === '' ? '' : Number(digits));
};
</script>

<template>
    <div class="relative">
        <input
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :value="modelValue"
            :disabled="disabled"
            :placeholder="placeholder"
            @input="onInput"
            @keydown.up.prevent="stepUp"
            @keydown.down.prevent="stepDown"
            class="w-full rounded-lg border py-2.5 pl-3.5 pr-12 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="error
                ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500'"
        />

        <!-- Stepper custom (pengganti spinner native) -->
        <div
            class="absolute inset-y-1 right-1 flex w-7 flex-col overflow-hidden rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"
            :class="{ 'pointer-events-none opacity-50': disabled }"
        >
            <button
                type="button"
                tabindex="-1"
                :disabled="!canStepUp"
                aria-label="Tambah nilai"
                @click="stepUp"
                class="flex flex-1 items-center justify-center text-gray-400 transition-colors hover:bg-gray-200/70 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300 disabled:pointer-events-none disabled:opacity-40"
            >
                <ChevronUp class="h-3 w-3" />
            </button>
            <button
                type="button"
                tabindex="-1"
                :disabled="!canStepDown"
                aria-label="Kurangi nilai"
                @click="stepDown"
                class="flex flex-1 items-center justify-center border-t border-gray-200 dark:border-gray-700 text-gray-400 transition-colors hover:bg-gray-200/70 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300 disabled:pointer-events-none disabled:opacity-40"
            >
                <ChevronDown class="h-3 w-3" />
            </button>
        </div>
    </div>
</template>