<script setup>
import { computed, ref, watch } from 'vue';
import { CheckCircle2, Clock, Wallet } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    status: { type: String, default: 'DONE' },
    /** `settle` = pilih lunas/belum, `lunas` = pelunasan langsung (tombol Tandai Lunas). */
    mode: { type: String, default: 'settle' },
    /** Servis sudah lunas: sembunyikan pilihan bayar, hanya catat penyerahan. */
    alreadyPaid: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    users: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
});

/** @emits confirm [payload: {paid: boolean, cashier: number|null}] */
const emit = defineEmits(['close', 'confirm']);

const isDirectPayment = computed(() => props.mode === 'lunas');
const isHandover = computed(() => !isDirectPayment.value && props.status === 'TAKEN');
const showPaymentChoice = computed(() => !isDirectPayment.value && !props.alreadyPaid);

const paid = ref(true);
const cashier = ref('');

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        paid.value = true;
        cashier.value = '';
    },
    { immediate: true },
);

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const title = computed(() => {
    if (isDirectPayment.value) {
        return 'Tandai Lunas';
    }

    return isHandover.value ? 'Ambil Unit Servis' : 'Servis Selesai';
});

const description = computed(() => {
    if (isDirectPayment.value) {
        return 'Pembayaran servis akan dicatat ke kas.';
    }

    if (isHandover.value) {
        return props.alreadyPaid
            ? 'Unit diambil customer. Servis sudah lunas, catat siapa yang menyerahkan.'
            : 'Unit diambil customer. Pilih status pembayaran servis.';
    }

    return 'Unit sudah selesai dikerjakan. Pilih status pembayaran servis.';
});

const userOptions = computed(() => props.users.map((user) => ({ id: user.id, label: user.name })));

/** Kasir dipilih saat unit diserahkan (TAKEN) atau saat pembayaran dicatat ke kas. */
const needsCashier = computed(() => isHandover.value || (showPaymentChoice.value && paid.value));
const cashierMissing = computed(() => needsCashier.value && !cashier.value);

const cashierLabel = computed(() => (isHandover.value ? 'Diserahkan oleh' : 'Kasir penerima'));
const cashierHint = computed(() => (isHandover.value
    ? 'Kasir yang menyerahkan unit ke customer. Namanya dicatat di kas sebagai penerima uang.'
    : 'Kasir yang menerima pembayaran. Namanya dicatat di kas sebagai penerima uang.'));

const handleConfirm = () => {
    if (cashierMissing.value) return;

    emit('confirm', {
        paid: isDirectPayment.value || props.alreadyPaid ? true : paid.value,
        cashier: cashier.value || null,
    });
};

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';

const choiceClass = (active) => [
    'inline-flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors',
    active
        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500 dark:bg-primary-900/30 dark:text-primary-300'
        : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
];
</script>

<template>
    <AppModal :show="show" :title="title" size="md" @close="emit('close')">
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>

            <div class="rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                <table class="w-full table-fixed text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/60 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Rincian</th>
                            <th class="px-3 py-2 text-right font-medium">Biaya</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="items.length === 0">
                            <td colspan="2" class="px-3 py-3 text-center text-gray-500">Belum ada rincian perbaikan.</td>
                        </tr>
                        <tr v-for="(item, i) in items" :key="i">
                            <td class="px-3 py-2 break-words text-gray-900 dark:text-white">{{ item.label }}</td>
                            <td class="w-32 px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ formatRupiah(item.cost) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <td class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                            <td class="w-32 px-3 py-2 text-right text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatRupiah(total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="showPaymentChoice">
                <label :class="labelClass">Status Pembayaran</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" :class="choiceClass(paid)" @click="paid = true">
                        <CheckCircle2 class="h-4 w-4" />
                        Lunas
                    </button>
                    <button type="button" :class="choiceClass(!paid)" @click="paid = false">
                        <Clock class="h-4 w-4" />
                        Belum Lunasi
                    </button>
                </div>
            </div>

            <div v-if="needsCashier">
                <label :class="labelClass">{{ cashierLabel }}</label>
                <AppDropdown v-model="cashier" :options="userOptions" placeholder="Pilih kasir" />
                <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">{{ cashierHint }}</p>
            </div>
        </div>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="emit('close')">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="saving" :disabled="cashierMissing" @click="handleConfirm">
                <Wallet class="h-4 w-4" />
                Simpan
            </AppButton>
        </template>
    </AppModal>
</template>
