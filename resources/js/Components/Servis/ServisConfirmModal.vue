<script setup>
import { computed, ref, watch } from 'vue';
import { Plus, Send, X } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    reparation: { type: Object, required: true },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({ stage_id: null, items: [{ label: '', cost: '', est_days: '' }], process_deadline_at: '', note: '' });
const editedMessage = ref(null);

const addItem = () => form.value.items.push({ label: '', cost: '', est_days: '' });
const removeItem = (index) => {
    if (form.value.items.length <= 1) return;
    form.value.items.splice(index, 1);
};

const formatDeadlineDate = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
    : '-');

const formatDateTimeInput = (value) => (value ? value.slice(0, 16).replace(' ', 'T') : '');

const defaultMessage = computed(() => {
    const items = form.value.items
        .filter((i) => i.label.trim())
        .map((i) => `  • ${i.label}: ${Number(i.cost) > 0 ? `Rp ${Number(i.cost).toLocaleString('id-ID')}` : '-'}${i.est_days ? ` (est. selesai ${i.est_days})` : ''}`);
    return [
        'Halo, dari R-TECH COMPUTER.',
        '',
        'Kami mau infokan untuk rincian kendala laptopnya dan biayanya. Berikut:',
        ...items,
        '',
        `Deadline proses: ${formatDeadlineDate(form.value.process_deadline_at)}`,
        '',
        'Mohon konfirmasinya ya.',
    ].join('\n');
});

const message = computed({
    get: () => editedMessage.value ?? defaultMessage.value,
    set: (value) => { editedMessage.value = value; },
});

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        const stages = props.reparation.stages ?? [];
        const firstStage = [...stages].reverse().find((stage) => stage.status === 'PENDING') ?? stages[0] ?? null;
        form.value = {
            stage_id: firstStage?.id ?? null,
            items: firstStage?.items?.length
                ? firstStage.items.map((i) => ({ label: i.label, cost: i.cost, est_days: i.est_days ?? '' }))
                : [{ label: '', cost: '', est_days: '' }],
            process_deadline_at: formatDateTimeInput(props.reparation.process_deadline_at),
            note: '',
        };
        editedMessage.value = null;
    },
    { immediate: true },
);

const whatsappNumber = computed(() => {
    const digits = (props.reparation.customer_phone ?? '').replace(/\D/g, '');
    if (digits.startsWith('0')) return `62${digits.slice(1)}`;
    return digits;
});

const sendWhatsapp = () => {
    const url = `https://wa.me/${whatsappNumber.value}?text=${encodeURIComponent(message.value)}`;
    window.open(url, '_blank', 'noopener');
};

const handleDone = () => {
    emit('submit', {
        stage_id: form.value.stage_id,
        items: form.value.items
            .filter((i) => i.label.trim() || i.cost !== '')
            .map((i) => ({
                label: i.label.trim(),
                cost: i.cost === '' ? 0 : Number(i.cost),
                est_days: i.est_days.trim() || null,
            })),
        process_deadline_at: form.value.process_deadline_at,
        note: form.value.note.trim() || null,
        message: message.value,
    });
};

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';
const inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 transition';
const textareaClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 transition';
</script>

<template>
    <AppModal :show="show" title="Konfirmasi ke Customer" size="lg" @close="$emit('close')">
        <div class="space-y-4">
            <div>
                <label :class="labelClass">Teks konfirmasi</label>
                <textarea v-model="message" rows="9" :class="textareaClass"></textarea>
                <p class="mt-1 text-xs text-gray-400">Teks ini akan dikirim lewat WhatsApp ke {{ reparation.customer_phone || 'nomor customer' }}.</p>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Detail Biaya</p>
                    <button type="button" class="flex items-center gap-1 text-xs font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700" @click="addItem">
                        <Plus class="h-3.5 w-3.5" />
                        Tambah
                    </button>
                </div>

                <div class="space-y-2.5">
                    <div v-for="(item, i) in form.items" :key="i" class="flex items-center gap-2">
                        <input v-model="item.label" type="text" placeholder="Kendala / pekerjaan"
                            class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500" />
                        <div class="w-36 shrink-0">
                            <AppCurrencyInput v-model="item.cost" placeholder="Biaya" />
                        </div>
                        <div class="w-36 shrink-0">
                            <AppDatePicker v-model="item.est_days" placeholder="Est. selesai" />
                        </div>
                        <button type="button" :disabled="form.items.length === 1"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-red-500 disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-gray-400"
                            @click="removeItem(i)" aria-label="Hapus baris">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label :class="labelClass">Deadline proses</label>
                <AppDatePicker v-model="form.process_deadline_at" placeholder="Pilih tanggal" />
            </div>

            <div>
                <label :class="labelClass">Catatan (opsional)</label>
                <textarea v-model="form.note" rows="3" maxlength="500" :class="textareaClass" placeholder="Catatan untuk log servis..."></textarea>
            </div>
        </div>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">Batal</AppButton>
            <AppButton variant="outline" size="sm" :disabled="!whatsappNumber" @click="sendWhatsapp">
                <Send class="h-4 w-4" />
                Kirim
            </AppButton>
            <AppButton variant="primary" size="sm" :loading="saving" @click="handleDone">Done</AppButton>
        </template>
    </AppModal>
</template>
