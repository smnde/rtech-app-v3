<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import { Calendar, ChevronLeft, ChevronRight, ChevronUp, ChevronDown, Clock } from 'lucide-vue-next';

/**
 * AppDatePicker — kalender custom sebagai pengganti <input type="date">.
 * Tampilan input date native berbeda-beda per browser dan tidak ikut tema
 * (terutama dark mode), jadi kalender dirender sendiri.
 *
 * v-model tetap string `YYYY-MM-DD` supaya kompatibel dengan pemakaian lama.
 */
const props = defineProps({
    modelValue:  { type: String, default: '' },
    placeholder: { type: String, default: 'Pilih tanggal...' },
    error:       { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
    withTime:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const MONTHS       = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
const WEEKDAYS     = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const open       = ref(false);
const triggerRef = ref(null);
const menuRef    = ref(null);
const menuStyle  = ref({});
const viewYear   = ref(new Date().getFullYear());
const viewMonth  = ref(new Date().getMonth());

// --- Helper tanggal ---
const parseValue = (value) => {
    if (!value) return null;

    const [datePart, timePart = ''] = value.split('T');
    const [y, m, d] = datePart.split('-').map(Number);
    const [hh = 0, mm = 0] = timePart.split(':').map(Number);

    return y && m && d ? new Date(y, m - 1, d, hh, mm) : null;
};

const toIsoDate = (date) =>
    [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');

const isSameDay = (a, b) =>
    !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

const todayIso     = toIsoDate(new Date());
const selectedDate = computed(() => parseValue(props.modelValue));

const pad = (n) => String(n).padStart(2, '0');

const timeValue = computed(() => {
    const d = selectedDate.value;
    return {
        hour:   d ? pad(d.getHours()) : '08',
        minute: d ? pad(d.getMinutes()) : '00',
    };
});

const emitWithTime = (date, hour, minute) => {
    emit('update:modelValue', `${toIsoDate(date)}T${pad(hour)}:${pad(minute)}`);
};

const stepTime = (unit, delta) => {
    const base = selectedDate.value ?? new Date();
    const hour = base.getHours();
    const minute = Math.floor(base.getMinutes() / 15) * 15;

    if (unit === 'hour') {
        emitWithTime(base, (hour + delta + 24) % 24, minute);
    } else {
        const total = hour * 60 + minute + delta * 15;
        const wrapped = (total + 24 * 60) % (24 * 60);
        emitWithTime(base, Math.floor(wrapped / 60), wrapped % 60);
    }
};

const displayLabel = computed(() => {
    const d = selectedDate.value;
    if (!d) return null;

    const date = `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
    return props.withTime ? `${date}, ${pad(d.getHours())}:${pad(d.getMinutes())}` : date;
});

// 6 baris x 7 kolom — selalu 42 sel biar tinggi kalender stabil
const calendarDays = computed(() => {
    const first = new Date(viewYear.value, viewMonth.value, 1);
    const start = new Date(viewYear.value, viewMonth.value, 1 - first.getDay());

    return Array.from({ length: 42 }, (_, i) => {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        return date;
    });
});

// Posisi popover dihitung manual (fixed) supaya tidak terpotong container overflow
const positionMenu = () => {
    const el = triggerRef.value;
    if (!el) return;

    const rect   = el.getBoundingClientRect();
    const width  = Math.max(rect.width, 288);
    const estH   = 340;
    const openUp = window.innerHeight - rect.bottom < estH + 8 && rect.top > estH + 8;

    menuStyle.value = {
        left:  `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`,
        width: `${width}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 6}px` }
            : { top: `${rect.bottom + 6}px` }),
    };
};

const onDocumentMousedown = (e) => {
    if (triggerRef.value?.contains(e.target) || menuRef.value?.contains(e.target)) return;
    closeMenu();
};

const openMenu = () => {
    if (props.disabled || open.value) return;

    const base = selectedDate.value ?? new Date();
    viewYear.value  = base.getFullYear();
    viewMonth.value = base.getMonth();

    open.value = true;

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

const toggleMenu = () => (open.value ? closeMenu() : openMenu());

const shiftMonth = (delta) => {
    const next = new Date(viewYear.value, viewMonth.value + delta, 1);
    viewYear.value  = next.getFullYear();
    viewMonth.value = next.getMonth();
};

const selectDay = (date) => {
    if (props.withTime) {
        const hour = timeValue.value.hour;
        const minute = timeValue.value.minute;
        emitWithTime(date, Number(hour), Number(minute));
    } else {
        emit('update:modelValue', toIsoDate(date));
        closeMenu();
        triggerRef.value?.focus();
    }
};

const selectToday = () => selectDay(new Date());

const clearValue = () => {
    emit('update:modelValue', '');
    closeMenu();
    triggerRef.value?.focus();
};

// Esc di sini di-stopPropagation supaya tidak ikut menutup modal induknya
const onKeydown = (e) => {
    if (e.key === 'Escape' && open.value) {
        e.stopPropagation();
        e.preventDefault();
        closeMenu();
    }
};

onBeforeUnmount(closeMenu);
</script>

<template>
    <div class="relative" @keydown="onKeydown">
        <!-- Trigger -->
        <button
            ref="triggerRef"
            type="button"
            :disabled="disabled"
            :aria-expanded="open"
            @click="toggleMenu"
            class="flex w-full items-center justify-between gap-2 rounded-lg border px-3.5 py-2.5 text-left text-sm transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
            :class="[
                error
                    ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                    : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600 focus:ring-primary-500',
                'bg-white dark:bg-gray-800',
                open ? 'ring-2' : '',
            ]"
        >
            <span
                class="truncate"
                :class="displayLabel ? 'text-gray-900 dark:text-white' : 'text-gray-400'"
            >
                {{ displayLabel ?? placeholder }}
            </span>
            <Calendar class="h-4 w-4 shrink-0 text-gray-400" />
        </button>

        <!-- Popover kalender (teleport biar tidak terpotong overflow container) -->
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
                    class="fixed z-[60] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-xl"
                    :style="menuStyle"
                >
                    <!-- Header: navigasi bulan -->
                    <div class="mb-2 flex items-center justify-between">
                        <button
                            type="button"
                            aria-label="Bulan sebelumnya"
                            @click="shiftMonth(-1)"
                            class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ MONTHS[viewMonth] }} {{ viewYear }}
                        </span>
                        <button
                            type="button"
                            aria-label="Bulan berikutnya"
                            @click="shiftMonth(1)"
                            class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Nama hari -->
                    <div class="mb-1 grid grid-cols-7">
                        <span
                            v-for="day in WEEKDAYS"
                            :key="day"
                            class="text-center text-[11px] font-medium uppercase text-gray-400"
                        >
                            {{ day }}
                        </span>
                    </div>

                    <!-- Grid tanggal -->
                    <div class="grid grid-cols-7 justify-items-center gap-y-0.5">
                        <button
                            v-for="date in calendarDays"
                            :key="date.getTime()"
                            type="button"
                            @click="selectDay(date)"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-sm transition-colors"
                            :class="[
                                isSameDay(date, selectedDate)
                                    ? 'bg-primary-600 font-semibold text-white hover:bg-primary-600 dark:hover:bg-primary-600'
                                    : date.getMonth() === viewMonth
                                        ? 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700'
                                        : 'text-gray-300 hover:bg-gray-100 dark:text-gray-600 dark:hover:bg-gray-700',
                                !isSameDay(date, selectedDate) && toIsoDate(date) === todayIso
                                    ? 'font-medium text-primary-600 ring-1 ring-inset ring-primary-500 dark:text-primary-400'
                                    : '',
                            ]"
                        >
                            {{ date.getDate() }}
                        </button>
                    </div>

                    <!-- Jam (hanya kalau withTime) -->
                    <div v-if="withTime" class="mt-3 flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/50">
                        <Clock class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Jam</span>
                        <div class="ml-auto flex items-center gap-1.5">
                            <div class="flex flex-col items-center">
                                <button type="button" aria-label="Tambah jam" @click="stepTime('hour', 1)"
                                    class="rounded-md p-0.5 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <span class="w-10 rounded-md bg-white py-1 text-center text-sm font-semibold tabular-nums text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-gray-700">{{ timeValue.hour }}</span>
                                <button type="button" aria-label="Kurangi jam" @click="stepTime('hour', -1)"
                                    class="rounded-md p-0.5 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <span class="pb-0.5 text-base font-semibold text-gray-400">:</span>
                            <div class="flex flex-col items-center">
                                <button type="button" aria-label="Tambah menit" @click="stepTime('minute', 1)"
                                    class="rounded-md p-0.5 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <span class="w-10 rounded-md bg-white py-1 text-center text-sm font-semibold tabular-nums text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-gray-700">{{ timeValue.minute }}</span>
                                <button type="button" aria-label="Kurangi menit" @click="stepTime('minute', -1)"
                                    class="rounded-md p-0.5 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-700">
                        <button
                            type="button"
                            @click="selectToday"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10"
                        >
                            Hari ini
                        </button>
                        <button
                            v-if="modelValue"
                            type="button"
                            @click="clearValue"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                        >
                            Bersihkan
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>