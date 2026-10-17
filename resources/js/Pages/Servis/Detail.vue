<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Layers, Plus, Printer, RefreshCw, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import ServisConfirmModal from '@/Components/Servis/ServisConfirmModal.vue';
import ServisStatusModal from '@/Components/Servis/ServisStatusModal.vue';
import ServisReceiptModal from '@/Components/Servis/ServisReceiptModal.vue';
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

const STAGE_COLOR = { PENDING: 'yellow', CONFIRMED: 'green', REJECTED: 'red', CANCELLED: 'gray' };

const formatDateTime = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    : '—');

const formatDateFull = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
    : '—');

const timeline = computed(() => [...(props.reparation.logs ?? [])].reverse());
const stages = computed(() => props.reparation.stages ?? []);

const showConfirm = ref(false);
const showStatus = ref(false);
const showAddStage = ref(false);
const showReceipt = ref(new URLSearchParams(window.location.search).has('print'));
const store = computed(() => page.props.store ?? {});
const saving = ref(false);

const canConfirm = computed(() => props.reparation.status !== 'TAKEN' && props.reparation.status !== 'CANCEL');

const addStageForm = ref({
    note: '',
    items: [{ label: '', cost: '', est_days: '' }],
});

const nextStageNo = computed(() => stages.value.length + 1);

const openAddStage = () => {
    addStageForm.value = { note: '', items: [{ label: '', cost: '', est_days: '' }] };
    showAddStage.value = true;
};

const closeAddStage = () => { showAddStage.value = false; };

const submitAddStage = () => {
    saving.value = true;
    router.post(route('servis.add-stage', props.reparation.id), {
        note: addStageForm.value.note.trim() || null,
        items: addStageForm.value.items.map((i) => ({
            label: i.label.trim(),
            cost: i.cost.trim(),
            est_days: i.est_days.trim() || null,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => { showAddStage.value = false; },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => { saving.value = false; },
    });
};

const addStageItem = () => addStageForm.value.items.push({ label: '', cost: '', est_days: '' });

const removeStageItem = (i) => {
    if (addStageForm.value.items.length <= 1) return;
    addStageForm.value.items.splice(i, 1);
};

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

const receiptMessage = computed(() => {
    const r = props.reparation;
    const s = page.props.store ?? {};
    const confirmedItems = (r.stages ?? [])
        .filter((st) => st.status === 'CONFIRMED')
        .flatMap((st) => st.items ?? []);
    const items = confirmedItems
        .filter((i) => i.label)
        .map((i) => `  • ${i.label}: ${i.cost || '-'}${i.est_days ? ` (est. selesai ${i.est_days})` : ''}`);
    return [
        `*${s.name ?? 'Rtech Computer'}*`,
        '',
        `*NOTA SERVIS* ${r.invoice ?? ''}`,
        `Tanggal: ${formatDateFull(r.created_at)}`,
        `Customer: ${r.customer_name ?? 'Umum'}`,
        '',
        `Perangkat: ${r.item_name}`,
        `Kerusakan: ${(r.flag ?? []).join(', ') || '-'}`,
        ...items,
        `Deadline proses: ${formatDateFull(r.process_deadline_at)}`,
        `Status: ${r.status}`,
        '',
        s.footer ?? 'Terima kasih',
    ].join('\n');
});
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
                    <AppButton variant="secondary" size="sm" @click="showReceipt = true">
                        <Printer class="h-4 w-4" />
                        Cetak Nota
                    </AppButton>
                    <AppButton v-if="can('edit reparation') && canConfirm" variant="secondary" size="sm" @click="openAddStage">
                        <Layers class="h-4 w-4" />
                        Tambah Tahap
                    </AppButton>
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
                            <AppBadge v-for="flag in reparation.flag" :key="flag" color="blue" size="sm">{{ flag }}</AppBadge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Deadline cek</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateFull(reparation.check_deadline_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500">Deadline proses</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateFull(reparation.process_deadline_at) }}</p>
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

                <!-- Tahap & biaya -->
                <div class="mt-5 space-y-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Tahap & Biaya</h2>

                    <div v-if="stages.length === 0" class="text-sm text-gray-500">Belum ada tahap.</div>

                    <div v-for="(stage, sIdx) in stages" :key="stage.id"
                        class="rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Tahap {{ sIdx + 1 }}</p>
                            <AppBadge :color="STAGE_COLOR[stage.status] ?? 'gray'" size="sm">{{ stage.status }}</AppBadge>
                        </div>

                        <div class="space-y-1">
                            <div v-for="(item, i) in stage.items" :key="i"
                                class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                                <span>{{ item.label }}</span>
                                <span class="text-gray-500">
                                    {{ item.cost }}
                                    <template v-if="item.est_days"> · est. {{ item.est_days }}</template>
                                </span>
                            </div>
                        </div>
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
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ log.description }}</p>
                            <span class="text-xs text-gray-500">{{ formatDateTime(log.occurred_at) }}</span>
                            <span v-if="log.user_name" class="text-xs text-gray-500">· {{ log.user_name }}</span>
                        </div>
                        <p v-if="log.note" class="mt-1 rounded-lg bg-gray-50 dark:bg-gray-800 px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ log.note }}</p>
                    </li>
                </ol>
            </div>
            </div>
        </div>
    </AppLayout>

    <ServisReceiptModal
        :show="showReceipt"
        :store="store"
        :reparation="reparation"
        :wa-text="receiptMessage"
        @close="showReceipt = false"
    />

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

    <AppModal :show="showAddStage" :title="`Tambah Tahap ${nextStageNo}`" @close="closeAddStage">
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Catatan <span class="text-gray-400">(opsional)</span>
                </label>
                <textarea
                    v-model="addStageForm.note"
                    rows="2"
                    placeholder="Temuan baru, penjelasan singkat..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Detail biaya & estimasi</label>
                    <button
                        type="button"
                        @click="addStageItem"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Tambah
                    </button>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="(item, idx) in addStageForm.items"
                        :key="idx"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="item.label"
                            type="text"
                            placeholder="Item / kerusakan"
                            class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                        <input
                            v-model="item.cost"
                            type="text"
                            placeholder="Biaya"
                            class="w-24 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                        <div class="w-36 shrink-0">
                            <AppDatePicker v-model="item.est_days" placeholder="Est. selesai" />
                        </div>
                        <button
                            v-if="addStageForm.items.length > 1"
                            type="button"
                            @click="removeStageItem(idx)"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <AppButton variant="ghost" size="md" @click="closeAddStage">Batal</AppButton>
            <AppButton variant="primary" size="md" :disabled="saving" @click="submitAddStage">
                <Layers class="h-4 w-4" />
                Tambah Tahap
            </AppButton>
        </template>
    </AppModal>
</template>
