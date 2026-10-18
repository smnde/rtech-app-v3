<script setup>
import { computed, ref, watch } from 'vue';
import { Loader2, Printer, Send } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import { useToast } from '@/composables/useToast';
import { waNumber } from '@/lib/whatsapp';
import { buildServiceReceiptPdf, printReceiptHtml, receiptFilename, sendReceiptToWhatsapp, storeHead, storeFoot, SERVICE_FOOTER_NOTES, warmReceiptLogo } from '@/lib/receipt';

const props = defineProps({
    show:       { type: Boolean, default: false },
    store:      { type: Object, default: () => ({}) },
    reparation: { type: Object, default: null },
    waText:     { type: String, default: '' },
});

const emit = defineEmits(['close']);
const toast = useToast();

// Muat logo sekali di awal supaya sudah siap saat kirim.
warmReceiptLogo();
watch(() => props.show, (v) => { if (v) warmReceiptLogo(); });

const FLAG_LABELS = { HW: 'Hardware', SW: 'Software', MB: 'Mainboard' };

const flags = computed(() => (props.reparation?.flag ?? []).map((f) => FLAG_LABELS[f] ?? f));

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d.replace(' ', 'T')).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const escape = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

// Koleksi item dari semua stage CONFIRMED — yang masuk nota.
const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const total = computed(() =>
    (props.reparation?.total_price ?? confirmedItems.value.reduce((sum, item) => sum + Number(item.cost ?? 0), 0)) || 0
);

const paymentLabel = computed(() => (props.reparation?.payment_status === 'paid' ? 'Lunas' : 'Belum lunas'));

const confirmedItems = computed(() => {
    const r = props.reparation;
    if (!r) return [];
    return (r.stages ?? [])
        .filter((s) => s.status === 'CONFIRMED')
        .flatMap((s) => s.items ?? []);
});

// Isi nota servis — dipakai bersama oleh tombol Cetak dan Kirim ke WA.
const receiptBody = computed(() => {
    const r = props.reparation;
    if (!r) return '';
    const s = props.store ?? {};
    const items = confirmedItems.value;

    return `
        ${storeHead({ store: s, title: 'Nota Servis', invoice: r.invoice ?? '' })}

        <div class="meta">
            <div class="pair"><dt>Tanggal</dt><dd>${formatDate(r.created_at)}</dd></div>
            <div class="pair"><dt>Customer</dt><dd>${escape(r.customer_name ?? 'Umum')}</dd></div>
            <div class="pair"><dt>Telepon</dt><dd>${escape(r.customer_phone ?? '-')}</dd></div>
            <div class="pair"><dt>Status</dt><dd>${escape(r.status ?? '-')}</dd></div>
        </div>

        <table class="items">
            <thead><tr>
                <th class="idx">#</th>
                <th>Kendala</th>
                <th class="num">Biaya</th>
            </tr></thead>
            <tbody>${items.map((item, i) => `
                <tr>
                    <td class="idx">${i + 1}</td>
                    <td class="item-name">${escape(item.label)}</td>
                    <td class="num">${Number(item.cost) > 0 ? formatRupiah(item.cost) : '-'}</td>
                </tr>`).join('')}
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Pembayaran</span><span>${paymentLabel.value}</span></div>
            <div class="row grand"><span>Total</span><span>${formatRupiah(total.value)}</span></div>
        </div>

        ${storeFoot(s, { notes: SERVICE_FOOTER_NOTES, bullet: true })}`;
});

const doPrint = () => {
    const r = props.reparation;
    if (!r) return;
    printReceiptHtml({ title: r.invoice ?? 'Nota Servis', body: receiptBody.value });
};

const sending = ref(false);

// Sama seperti nota penjualan: PDF → unduh → buka chat WhatsApp customer.
const sendWa = async () => {
    const r = props.reparation;
    if (!waNumber(r?.customer_phone)) {
        toast.error('Nomor telepon customer tidak tersedia.');
        return;
    }

    sending.value = true;
    try {
        const pdf = await buildServiceReceiptPdf({
            store: props.store,
            reparation: r,
            items: confirmedItems.value,
            total: total.value,
            paid: r.payment_status === 'paid',
            filename: receiptFilename(r.customer_name, r.invoice),
        });
        await sendReceiptToWhatsapp({ pdf, phone: r.customer_phone, message: props.waText });
        toast.success('Nota PDF diunduh — lampirkan di chat WhatsApp yang terbuka.');
        emit('close');
    } catch (e) {
        toast.error('Gagal membuat PDF nota.');
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <AppModal :show="show" title="Nota Servis" size="lg" @close="emit('close')">
        <!-- Preview nota lebar -->
        <div v-if="reparation"
            class="mx-auto w-full max-w-[560px] rounded-sm bg-white p-6 text-[10px] leading-relaxed text-slate-800 shadow-lg ring-1 ring-slate-200">
            <div class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-4">
                <div class="flex items-center gap-2">
                    <img src="/icons/apple-touch-icon.png" alt="logo" class="h-10 w-10 rounded-lg object-contain shrink-0" />
                    <div>
                        <p class="text-[15px] font-bold tracking-wide text-slate-900">{{ store.name || 'Toko' }}</p>
                        <p v-if="store.address" class="text-[9px] text-slate-500">{{ store.address }}</p>
                        <p v-if="store.phone" class="text-[9px] text-slate-500">{{ store.phone }}</p>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-600">Nota Servis</p>
                    <p class="font-mono text-[12px] font-bold text-slate-900">{{ reparation.invoice }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-x-6 py-3 text-[9.5px]">
                <div class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Tanggal</span><span class="text-right font-medium">{{ formatDate(reparation.created_at) }}</span>
                </div>
                <div class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Customer</span><span class="text-right font-medium">{{ reparation.customer_name ?? 'Umum' }}</span>
                </div>
                <div v-if="reparation.customer_phone" class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Telepon</span><span class="text-right font-medium tabular-nums">{{ reparation.customer_phone }}</span>
                </div>
                <div class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Status</span><span class="text-right font-medium">{{ reparation.status }}</span>
                </div>
            </div>

            <table class="w-full border-collapse text-[9.5px]">
                <thead>
                    <tr class="border-y border-slate-300 bg-slate-50 text-[8px] uppercase tracking-wide text-slate-500">
                        <th class="w-6 px-1 py-1.5 text-left font-semibold">#</th>
                        <th class="px-1 py-1.5 text-left font-semibold">Kendala</th>
                        <th class="px-1 py-1.5 text-right font-semibold">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in confirmedItems" :key="i" class="border-b border-slate-100 align-top">
                        <td class="px-1 py-1.5 text-slate-400">{{ i + 1 }}</td>
                        <td class="px-1 py-1.5 font-semibold text-slate-900">{{ item.label }}</td>
                        <td class="px-1 py-1.5 text-right tabular-nums">{{ Number(item.cost) > 0 ? formatRupiah(item.cost) : '-' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-2 border-t border-slate-300 pt-2 text-[9.5px]">
                <div class="flex justify-between py-0.5 font-semibold text-slate-900"><span>Total</span><span class="tabular-nums">{{ formatRupiah(total) }}</span></div>
                <div class="flex justify-between py-0.5 text-slate-500"><span>Pembayaran</span><span>{{ paymentLabel }}</span></div>
            </div>

            <p class="mt-6 border-t border-dashed border-slate-300 pt-3 text-center text-[9px] text-slate-500">
                {{ store.footer || 'Terima kasih' }}
            </p>
            <p class="mt-2 text-center text-[7.5px] text-slate-400 leading-relaxed">
                Garansi dengan kerusakan yg sama 1 bln.<br>
                Garansi berlaku selama tidak terjadi human error / kesalahan pengguna.<br>
                Tidak ada pengembalian uang untuk Servisan yang sudah selesai.<br>
                Servisan yang statusnya selesai atau cancel, jika tidak diambil lebih dr 60hr, di luar tanggung jawab kami.
            </p>
        </div>

        <template #footer>
            <button type="button" @click="emit('close')"
                class="px-4 py-2.5 text-sm font-medium rounded-lg transition-colors text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700">
                Batal
            </button>
            <button type="button" :disabled="!reparation?.customer_phone || sending" @click="sendWa"
                class="flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-50">
                <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                <Send v-else class="h-4 w-4" />
                Kirim ke WA
            </button>
            <button type="button" @click="doPrint"
                class="flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white rounded-lg transition-colors bg-indigo-600 hover:bg-indigo-500">
                <Printer class="h-4 w-4" />
                Cetak
            </button>
        </template>
    </AppModal>
</template>
