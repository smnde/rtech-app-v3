<script setup>
import { ref, watch } from 'vue';

/**
 * AppCurrencyInput — input harga dengan prefix "Rp" dan format ribuan
 * (1000000 → "1.000.000").
 * v-model tetap number murni ('' kalau kosong) supaya logika form
 * yang sudah ada tidak perlu berubah.
 */
const props = defineProps({
    modelValue:  { type: [String, Number], default: '' },
    placeholder: { type: String, default: '0' },
    error:       { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const formatter = new Intl.NumberFormat('id-ID');
const display   = ref('');

// String desimal dari backend ("2500000.00") harus dibaca sebagai 2500000,
// bukan 250000000 — buang bagian pecahan sebelum membersihkan non-digit.
const digitsOf = (value) => String(value ?? '').split('.')[0].replace(/\D/g, '');

const format = (digits) => (digits === '' ? '' : formatter.format(Number(digits)));

watch(
    () => props.modelValue,
    (val) => {
        const digits = digitsOf(val);
        // Skip kalau tampilan sudah merepresentasikan angka yang sama
        // (mis. user baru saja mengetik dan angkanya baru diformat)
        if (digitsOf(display.value) !== digits) {
            display.value = format(digits);
        }
    },
    { immediate: true },
);

const onInput = (e) => {
    const digits = e.target.value.replace(/\D/g, '');
    display.value = format(digits);
    emit('update:modelValue', digits === '' ? '' : Number(digits));
};
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-gray-500 dark:text-gray-400">
            Rp
        </span>
        <input
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :value="display"
            :disabled="disabled"
            :placeholder="placeholder"
            @input="onInput"
            class="w-full rounded-lg border py-2.5 pl-10 pr-3.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="error
                ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500'"
        />
    </div>
</template>