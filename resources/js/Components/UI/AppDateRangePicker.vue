<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import { Calendar, ChevronLeft, ChevronRight, X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ from: '', to: '' }) },
    placeholder: { type: String, default: 'Pilih rentang tanggal...' },
});

const emit = defineEmits(['update:modelValue']);

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
const WEEKDAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const open = ref(false);
const triggerRef = ref(null);
const menuStyle = ref({});
const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());
const picking = ref('from');

const parseValue = (value) => {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    return y && m && d ? new Date(y, m - 1, d) : null;
};

const toIsoDate = (date) =>
    [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');

const formatLabel = (value) => {
    const date = parseValue(value);
    return date ? `${date.getDate()} ${MONTHS_SHORT[date.getMonth()]} ${date.getFullYear()}` : null;
};

const fromDate = computed(() => parseValue(props.modelValue?.from));
const toDate = computed(() => parseValue(props.modelValue?.to));

const displayLabel = computed(() => {
    const from = formatLabel(props.modelValue?.from);
    const to = formatLabel(props.modelValue?.to);

    if (from && to) return `${from} – ${to}`;
    if (from) return `Dari ${from}`;
    if (to) return `Sampai ${to}`;
    return null;
});

const isInRange = (date) => !!fromDate.value && !!toDate.value && date > fromDate.value && date < toDate.value;

const isSameDay = (a, b) =>
    !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

const calendarDays = computed(() => {
    const first = new Date(viewYear.value, viewMonth.value, 1);
    const start = new Date(viewYear.value, viewMonth.value, 1 - first.getDay());

    return Array.from({ length: 42 }, (_, i) => {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        return date;
    });
});

const positionMenu = () => {
    const el = triggerRef.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const width = Math.max(rect.width, 288);
    const openUp = window.innerHeight - rect.bottom < 348 && rect.top > 348;

    menuStyle.value = {
        left: `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`,
        width: `${width}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 6}px` }
            : { top: `${rect.bottom + 6}px` }),
    };
};

const onDocumentMousedown = (event) => {
    if (triggerRef.value?.contains(event.target) || event.target.closest?.('[data-date-range-menu]')) return;
    closeMenu();
};

const openMenu = () => {
    if (open.value) return;

    const base = fromDate.value ?? new Date();
    viewYear.value = base.getFullYear();
    viewMonth.value = base.getMonth();
    picking.value = fromDate.value && !toDate.value ? 'to' : 'from';
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
    viewYear.value = next.getFullYear();
    viewMonth.value = next.getMonth();
};

const selectDay = (date) => {
    const iso = toIsoDate(date);

    if (picking.value === 'from') {
        emit('update:modelValue', { from: iso, to: '' });
        picking.value = 'to';
        return;
    }

    const from = props.modelValue?.from;
    emit('update:modelValue', from && iso < from ? { from: iso, to: from } : { from, to: iso });
    closeMenu();
};

const clearValue = () => {
    emit('update:modelValue', { from: '', to: '' });
    picking.value = 'from';
};

onBeforeUnmount(closeMenu);
</script>

<template>
    <div class="relative">
        <button
            ref="triggerRef"
            type="button"
            :aria-expanded="open"
            @click="toggleMenu"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-left text-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600"
            :class="open ? 'ring-2 ring-primary-500' : ''"
        >
            <span class="truncate" :class="displayLabel ? 'text-gray-900 dark:text-white' : 'text-gray-400'">
                {{ displayLabel ?? placeholder }}
            </span>
            <Calendar class="h-4 w-4 shrink-0 text-gray-400" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                data-date-range-menu
                class="fixed z-[60] rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                :style="menuStyle"
            >
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ picking === 'from' ? 'Pilih tanggal awal' : 'Pilih tanggal akhir' }}
                </p>

                <div class="mb-2 flex items-center justify-between">
                    <button
                        type="button"
                        aria-label="Bulan sebelumnya"
                        @click="shiftMonth(-1)"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
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
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <div class="mb-1 grid grid-cols-7">
                    <span v-for="day in WEEKDAYS" :key="day" class="text-center text-[11px] font-medium uppercase text-gray-400">
                        {{ day }}
                    </span>
                </div>

                <div class="grid grid-cols-7 justify-items-center gap-y-0.5">
                    <button
                        v-for="date in calendarDays"
                        :key="date.getTime()"
                        type="button"
                        @click="selectDay(date)"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-sm transition-colors"
                        :class="[
                            isSameDay(date, fromDate) || isSameDay(date, toDate)
                                ? 'bg-primary-600 font-semibold text-white'
                                : isInRange(date)
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
                                    : date.getMonth() === viewMonth
                                        ? 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700'
                                        : 'text-gray-300 hover:bg-gray-100 dark:text-gray-600 dark:hover:bg-gray-700',
                        ]"
                    >
                        {{ date.getDate() }}
                    </button>
                </div>

                <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-700">
                    <button
                        type="button"
                        @click="clearValue"
                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                    >
                        <X class="h-3.5 w-3.5" />
                        Bersihkan
                    </button>
                    <button
                        type="button"
                        @click="closeMenu"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10"
                    >
                        Selesai
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
