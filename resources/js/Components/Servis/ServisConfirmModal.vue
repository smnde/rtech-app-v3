<script setup>
import { computed, ref, watch } from 'vue';
import { Send } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    reparation: { type: Object, required: true },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({ estimated_cost: '', process_deadline_at: '' });
const editedMessage = ref(null);

const workingDaysUntil = (value) => {
    if (!value) return 0;
    const target = new Date(value.replace(' ', 'T'));
    const cursor = new Date();
    cursor.setHours(0, 0, 0, 0);
    let days = 0;
    while (cursor < target) {
        cursor.setDate(cursor.getDate() + 1);
        if (cursor.getDay() !== 0 && cursor.getDay() !== 6 && cursor <= target) days += 1;
    }
    return days;
};

const formatDeadlineDate = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '-');

const defaultMessage = computed(() => [
    'Selamat ........ Dari Rtech Computer.',
    '',
    'mau mengkonfirmasikan, untuk laptopnya sudah dicek. kendala di .........',
    `biaya perbaikan ${form.value.estimated_cost || '...'} dan waktu pengerjaan ${workingDaysUntil(form.value.process_deadline_at)} hari kerja.`,
    `diperkirakan paling lambatnya ${formatDeadlineDate(form.value.process_deadline_at)}.`,
    '',
    'apa boleh dilanjutkan?',
].join('\n'));

const message = computed({
    get: () => editedMessage.value ?? defaultMessage.value,
    set: (value) => { editedMessage.value = value; },
});

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        form.value = {
            estimated_cost: props.reparation.estimated_cost ?? '',
            process_deadline_at: props.reparation.process_deadline_at ?? '',
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
        estimated_cost: form.value.estimated_cost,
        process_deadline_at: form.value.process_deadline_at,
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

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label :class="labelClass">Estimasi biaya</label>
                    <input v-model="form.estimated_cost" type="text" placeholder="Contoh: 400-500rb" :class="inputClass" />
                </div>
                <div>
                    <label :class="labelClass">Deadline proses</label>
                    <AppDatePicker v-model="form.process_deadline_at" with-time placeholder="Pilih tanggal & jam" />
                </div>
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
