<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, RefreshCw } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import ServisConfirmModal from '@/Components/Servis/ServisConfirmModal.vue';
import ServisStatusModal from '@/Components/Servis/ServisStatusModal.vue';
import { usePermission } from '@/composables/usePermission';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    reparation: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
});

const { can } = usePermission();
const toast = useToast();
const page = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const FLAG_LABELS = { HW: 'Hardware', SW: 'Software', MB: 'Mainboard' };

const STATUS_COLOR = {
    RECEIVED: 'gray',
    CHECKED: 'blue',
    CONFIRMED: 'blue',
    'WAITING CONFIRMATION': 'yellow',
    'ON PROCESS': 'purple',
    PENDING: 'yellow',
    CANCEL: 'red',
    DONE: 'green',
    TAKEN: 'green',
};

const formatDateTime = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const EVENT_LABELS = {
    RECEIVED: 'Servis diterima',
    STATUS_CHANGED: 'Status berubah',
    DEADLINE_CHANGED: 'Deadline diubah',
    ESTIMATE_CHANGED: 'Estimasi diubah',
    MESSAGE_SENT: 'Pesan dikirim',
};

const timeline = computed(() => [...props.reparation.logs].reverse());

const showConfirm = ref(false);
const showStatus = ref(false);
const saving = ref(false);

const canConfirm = computed(() => props.reparation.status !== 'TAKEN' && props.reparation.status !== 'CANCEL');

const submitConfirm = (payload) => {
    saving.value = true;
    router.patch(route('servis.confirm', props.reparation.id), payload, {
        preserveScroll: true,
        onSuccess: () => { showConfirm.value = false; },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    });
};

const submitStatus = (payload) => {
    saving.value = true;
    router.patch(route('servis.status', props.reparation.id), payload, {
        preserveScroll: true,
        onSuccess: () => { showStatus.value = false; },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    });
};

const cardClass = 'rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900';
</script>

<template>
    <Head :title="`Servis ${reparation.invoice ?? ''}`" />

    <AppLayout title="Detail Servis">
        <div class="flex-1 min-h-0 flex flex-col gap-6">
            <div class="shrink-0 flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('servis.index')" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 dark:hover:text-white">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke daftar servis
                </Link>

                <div class="flex items-center gap-2">
                    <AppButton v-if="can('edit reparation') && canConfirm" variant="secondary" size="sm" @click="showConfirm = true">
                        <CheckCircle2 class="h-4 w-4" />
                        Konfirmasi
                    </AppButton>
                    <AppButton v-if="can('edit reparation')" variant="primary" size="sm" @click="showStatus = true">
                        <RefreshCw class="h-4 w-4" />
                        Ubah Status
                    </AppButton>
                </div>
            </div>

            <div class="scrollbar-styled scrollbar-mobile-hidden flex-1 min-h-0 overflow-y-auto space-y-6 pb-4">
            <div :class="[cardClass, 'p-5']">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-mono text-xs text-gray-500">{{ reparation.invoice ?? '—' }}</p>
                        <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ reparation.item_name }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ reparation.customer_name }} · {{ reparation.customer_phone }}</p>
                    </div>
                    <AppBadge :color="STATUS_COLOR[reparation.status] ?? 'gray'">{{ reparation.status }}</AppBadge>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Jenis kerusakan</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <AppBadge v-for="flag in reparation.flag" :key="flag" color="blue" size="sm">{{ FLAG_LABELS[flag] ?? flag }}</AppBadge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Estimasi biaya</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ reparation.estimated_cost || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Deadline cek</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateTime(reparation.check_deadline_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Deadline proses</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateTime(reparation.process_deadline_at) }}</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Keluhan</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ reparation.complaint.join(', ') || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Kondisi fisik</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ reparation.condition.join(', ') || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Aksesoris</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ reparation.accessories.join(', ') || '—' }}</p>
                    </div>
                </div>

                <div v-if="reparation.technician_notes || reparation.additional_notes" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-if="reparation.technician_notes">
                        <p class="text-xs uppercase tracking-wider text-gray-500">Catatan teknisi</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ reparation.technician_notes }}</p>
                    </div>
                    <div v-if="reparation.additional_notes">
                        <p class="text-xs uppercase tracking-wider text-gray-500">Catatan tambahan</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ reparation.additional_notes }}</p>
                    </div>
                </div>
            </div>

            <div :class="[cardClass, 'p-5']">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Riwayat</h2>

                <p v-if="timeline.length === 0" class="text-sm text-gray-500">Belum ada riwayat.</p>

                <ol v-else class="relative border-l border-gray-200 dark:border-gray-800 space-y-5 pl-5">
                    <li v-for="(log, index) in timeline" :key="index" class="relative">
                        <span class="absolute -left-[25px] top-1.5 h-2.5 w-2.5 rounded-full bg-primary-500"></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ EVENT_LABELS[log.event] ?? log.event ?? 'Catatan' }}</p>
                            <span class="text-xs text-gray-500">{{ formatDateTime(log.occurred_at) }}</span>
                            <span v-if="log.user_name" class="text-xs text-gray-500">· {{ log.user_name }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ log.description }}</p>
                        <p v-if="log.note" class="mt-1 rounded-lg bg-gray-50 dark:bg-gray-800 px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ log.note }}</p>
                    </li>
                </ol>
            </div>
            </div>
        </div>
    </AppLayout>

    <ServisConfirmModal
        :show="showConfirm"
        :reparation="reparation"
        :saving="saving"
        @close="showConfirm = false"
        @submit="submitConfirm"
    />

    <ServisStatusModal
        :show="showStatus"
        :reparation="reparation"
        :statuses="statuses"
        :saving="saving"
        @close="showStatus = false"
        @submit="submitStatus"
    />
</template>
