<script setup>
import { ref, computed, nextTick, watch, onBeforeUnmount } from 'vue';
import { Check, Search } from 'lucide-vue-next';

/**
 * AppSelect — dropdown custom dengan live search (combobox).
 * Klik belum membuka daftar; daftar muncul begitu user mengetik
 * dan otomatis difilter sesuai kata kunci.
 *
 * Opsi bisa berupa string ('OWNER') atau objek { id, label } —
 * nilai yang di-emit selalu `id` (atau string itu sendiri).
 */
const props = defineProps({
    modelValue:  { type: [String, Number], default: '' },
    options:     { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih...' },
    error:       { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const open        = ref(false);
const query       = ref('');
const activeIndex = ref(0);
const rootRef     = ref(null);
const inputRef    = ref(null);
const menuRef     = ref(null);
const menuStyle   = ref({});

// Normalisasi: string → { value, label }, objek { id, label } → { value, label }
const normalizedOptions = computed(() =>
    props.options.map((o) =>
        typeof o === 'object' && o !== null
            ? { value: o.id, label: o.label }
            : { value: o, label: String(o) },
    ),
);

const selectedOption = computed(
    () => normalizedOptions.value.find((o) => o.value === props.modelValue) ?? null,
);

// Live search — cocokkan label dengan kata kunci yang diketik
const filteredOptions = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return normalizedOptions.value;

    return normalizedOptions.value.filter((o) => o.label.toLowerCase().includes(q));
});

// Kembalikan teks input ke label opsi terpilih (atau kosong)
const syncQueryToSelected = () => {
    query.value = selectedOption.value?.label ?? '';
};

watch(() => props.modelValue, syncQueryToSelected, { immediate: true });

// Posisi menu dihitung manual (fixed) supaya tidak terpotong container overflow
const positionMenu = () => {
    const el = inputRef.value;
    if (!el) return;

    const rect   = el.getBoundingClientRect();
    const estH   = Math.min(filteredOptions.value.length * 38 + 12, 260);
    const openUp = window.innerHeight - rect.bottom < estH + 8 && rect.top > estH + 8;

    menuStyle.value = {
        left:  `${rect.left}px`,
        width: `${rect.width}px`,
        maxHeight: '260px',
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 6}px` }
            : { top: `${rect.bottom + 6}px` }),
    };
};

const onDocumentMousedown = (e) => {
    if (rootRef.value?.contains(e.target) || menuRef.value?.contains(e.target)) return;
    closeMenu();
};

const openMenu = () => {
    if (props.disabled || open.value) return;

    open.value = true;
    const idx = filteredOptions.value.findIndex((o) => o.value === props.modelValue);
    activeIndex.value = idx >= 0 ? idx : 0;

    nextTick(() => {
        positionMenu();
        window.addEventListener('scroll', positionMenu, true);
        window.addEventListener('resize', positionMenu);
        document.addEventListener('mousedown', onDocumentMousedown);
    });
};

const closeMenu = () => {
    if (!open.value) return;

    open.value = false;
    window.removeEventListener('scroll', positionMenu, true);
    window.removeEventListener('resize', positionMenu);
    document.removeEventListener('mousedown', onDocumentMousedown);
};

const highlightActive = () => {
    nextTick(() => {
        menuRef.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
    });
};

const moveActive = (delta) => {
    const len = filteredOptions.value.length;
    if (!len) return;

    activeIndex.value = Math.min(Math.max(activeIndex.value + delta, 0), len - 1);
    highlightActive();
};

// Daftar hanya muncul saat user mengetik
const onInput = (e) => {
    query.value = e.target.value;

    if (!query.value.trim()) {
        closeMenu();
        return;
    }

    if (!open.value) {
        openMenu();
    } else {
        activeIndex.value = 0;
    }
};

// Select all saat fokus — user tinggal ngetik buat ganti nilai yang terpilih
const onFocus = (e) => e.target.select();

const onBlur = () => {
    syncQueryToSelected();
    closeMenu();
};

const selectOption = (opt) => {
    emit('update:modelValue', opt.value);
    query.value = opt.label;
    closeMenu();
    inputRef.value?.focus();
};

// Esc di-stopPropagation supaya tidak ikut menutup modal induknya
const onKeydown = (e) => {
    if (props.disabled) return;

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            if (open.value) moveActive(1);
            else openMenu();
            break;
        case 'ArrowUp':
            e.preventDefault();
            if (open.value) moveActive(-1);
            break;
        case 'Enter':
            if (!open.value) return;
            e.preventDefault();
            if (filteredOptions.value[activeIndex.value]) {
                selectOption(filteredOptions.value[activeIndex.value]);
            }
            break;
        case 'Escape':
            if (!open.value) return;
            e.stopPropagation();
            e.preventDefault();
            syncQueryToSelected();
            closeMenu();
            break;
        case 'Tab':
            closeMenu();
            break;
    }
};

onBeforeUnmount(closeMenu);
</script>

<template>
    <div ref="rootRef" class="relative" @keydown="onKeydown">
        <input
            ref="inputRef"
            type="text"
            role="combobox"
            autocomplete="off"
            spellcheck="false"
            :value="query"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-expanded="open"
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
            class="w-full rounded-lg border py-2.5 pl-3.5 pr-10 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="error
                ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600 focus:ring-primary-500'"
        />
        <Search class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />

        <!-- Menu (teleport biar tidak terpotong overflow container) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="open"
                    ref="menuRef"
                    class="fixed z-[60] overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl"
                    :style="menuStyle"
                    @mousedown.prevent
                >
                    <ul class="max-h-60 overflow-y-auto scrollbar-styled p-1" role="listbox">
                        <li v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-gray-400">
                            Tidak ada hasil untuk "{{ query }}"
                        </li>
                        <li
                            v-for="(opt, i) in filteredOptions"
                            :key="opt.value"
                            role="option"
                            :aria-selected="opt.value === modelValue"
                            :data-active="activeIndex === i"
                            @click="selectOption(opt)"
                            @mousemove="activeIndex = i"
                            class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="[
                                opt.value === modelValue
                                    ? 'font-medium text-primary-700 dark:text-primary-400'
                                    : 'text-gray-700 dark:text-gray-200',
                                activeIndex === i ? 'bg-gray-100 dark:bg-gray-700/70' : '',
                            ]"
                        >
                            <span class="flex-1 truncate">{{ opt.label }}</span>
                            <Check
                                v-if="opt.value === modelValue"
                                class="h-4 w-4 shrink-0"
                            />
                        </li>
                    </ul>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>