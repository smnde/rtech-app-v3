<script setup>
import { computed, ref, watch } from 'vue';
import { Loader2, Printer, Send } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import { buildSaleReceiptPdf, printReceipt, receiptFilename, saleBody, sendReceiptToWhatsapp, warmReceiptLogo } from '@/lib/receipt';
import { waNumber } from '@/lib/whatsapp';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    show:   { type: Boolean, default: false },
    store:  { type: Object, default: () => ({}) },
    sale:   { type: Object, default: null },
    title:  { type: String, default: 'NOTA PENJUALAN' },
    phone:  { type: String, default: '' },
    waText: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const toast = useToast();

// Muat logo sekali di awal supaya sudah siap saat kirim.
warmReceiptLogo();
watch(() => props.show, (v) => { if (v) warmReceiptLogo(); });

const items = computed(() => props.sale?.sale_items || props.sale?.items || []);
const isPaid = computed(() =>
    props.sale?.payment_status ? props.sale.payment_status === 'lunas' : true
);
const isPurchase = computed(() => props.sale?.supplier !== undefined);
const hasDiscount = computed(() => Number(props.sale?.discount) > 0);
const itemDiscount = computed(() => items.value.reduce((s, li) => s + (Number(li.discount) || 0), 0));
const invoiceNo = computed(() => props.sale?.invoice_no ?? props.sale?.reference_no ?? '');
const partyLabel = computed(() => (isPurchase.value ? 'Supplier' : 'Customer'));
const partyName = computed(() => props.sale?.customer?.name ?? props.sale?.supplier ?? 'Umum');
const phone2 = computed(() => props.sale?.customer?.phone ?? props.sale?.supplier_phone ?? '');

const num = (v) => new Intl.NumberFormat('id-ID').format(Number(v) || 0);
const money = (v) => `Rp ${num(v)}`;

function formatDate(d) {
    if (!d) return '';
    return new Date(String(d).replace(' ', 'T')).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function formatSpec(spec) {
    if (!spec || typeof spec !== 'object') return '';
    return Object.entries(spec)
        .filter(([, v]) => v !== null && v !== undefined && `${v}`.trim() !== '')
        .map(([k, v]) => `${k.charAt(0).toUpperCase()}${k.slice(1)}: ${v}`)
        .join(' · ');
}

const doPrint = () => printReceipt({ store: props.store, sale: props.sale, title: props.title });

const sending = ref(false);

// Nota digambar langsung ke PDF (vector): tidak ada canvas, jadi tidak bisa
// terpotong di layar HP dan teksnya tetap tajam.
const pdfOf = () => buildSaleReceiptPdf({
    store: props.store,
    sale: props.sale,
    title: props.title,
    filename: receiptFilename(partyName.value, invoiceNo.value),
});

// Generate PDF → unduh → buka chat WhatsApp nomor customer.
const sendWa = async () => {
    if (!waNumber(props.phone)) {
        toast.error('Nomor telepon tidak tersedia.');
        return;
    }

    sending.value = true;
    try {
        const pdf = await pdfOf();
        await sendReceiptToWhatsapp({ pdf, phone: props.phone, message: props.waText });
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
    <AppModal :show="show" :title="title" size="lg" @close="emit('close')">
        <!-- Preview nota lebar (A4 portrait, tinggi ikut isi) -->
        <div v-if="sale"
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
                    <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-600">{{ title }}</p>
                    <p class="font-mono text-[12px] font-bold text-slate-900">{{ invoiceNo }}</p>
                    <span v-if="sale.payment_status"
                        class="mt-1 inline-block rounded-full px-2 py-0.5 text-[8px] font-bold tracking-wide"
                        :class="isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                        {{ isPaid ? 'LUNAS' : 'BELUM LUNAS' }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-x-6 py-3 text-[9.5px]">
                <div class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Tanggal</span><span class="text-right font-medium">{{ formatDate(sale.created_at) }}</span>
                </div>
                <div v-if="sale.user?.name" class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Kasir</span><span class="text-right font-medium">{{ sale.user.name }}</span>
                </div>
                <div class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">{{ partyLabel }}</span><span class="text-right font-medium">{{ partyName }}</span>
                </div>
                <div v-if="phone2" class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Telepon</span><span class="text-right font-medium tabular-nums">{{ phone2 }}</span>
                </div>
                <div v-if="sale.payment_method" class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">Pembayaran</span><span class="text-right font-medium">{{ sale.payment_method }}</span>
                </div>
                <div v-if="sale.dp_amount" class="flex justify-between gap-4 border-b border-dotted border-slate-200 py-1">
                    <span class="text-slate-500">DP</span><span class="text-right font-medium">{{ money(sale.dp_amount) }}</span>
                </div>
            </div>

            <table class="w-full border-collapse text-[9.5px]">
                <thead>
                    <tr class="border-y border-slate-300 bg-slate-50 text-[8px] uppercase tracking-wide text-slate-500">
                        <th class="w-6 px-1 py-1.5 text-left font-semibold">#</th>
                        <th class="px-1 py-1.5 text-left font-semibold">Barang</th>
                        <th class="px-1 py-1.5 text-right font-semibold">Qty</th>
                        <th class="px-1 py-1.5 text-right font-semibold">Harga</th>
                        <th class="px-1 py-1.5 text-right font-semibold">Diskon</th>
                        <th class="px-1 py-1.5 text-right font-semibold">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(li, i) in items" :key="i" class="border-b border-slate-100 align-top">
                        <td class="px-1 py-1.5 text-slate-400">{{ i + 1 }}</td>
                        <td class="px-1 py-1.5">
                            <p class="font-semibold text-slate-900">{{ li.item_name ?? li.name }}</p>
                            <p v-if="li.item_code || li.serial_number" class="text-[8px] text-slate-500">
                                <span v-if="li.item_code">{{ li.item_code }}</span>
                                <span v-if="li.item_code && li.serial_number"> · </span>
                                <span v-if="li.serial_number">SN: {{ li.serial_number }}</span>
                            </p>
                            <p v-if="formatSpec(li.specification)" class="text-[7.5px] text-slate-400">{{ formatSpec(li.specification) }}</p>
                        </td>
                        <td class="px-1 py-1.5 text-right tabular-nums">{{ li.quantity ?? li.qty }}</td>
                        <td class="px-1 py-1.5 text-right tabular-nums">{{ num(li.price ?? li.buy_price) }}</td>
                        <td class="px-1 py-1.5 text-right tabular-nums">{{ Number(li.discount) > 0 ? `− ${num(li.discount)}` : '—' }}</td>
                        <td class="px-1 py-1.5 text-right tabular-nums">{{ num(li.subtotal ?? ((li.quantity ?? li.qty) * (li.price ?? li.buy_price))) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="ml-auto mt-4 w-52 text-[9.5px]">
                <div class="flex justify-between py-1"><span class="text-slate-500">Subtotal</span><span>{{ money(sale.subtotal) }}</span></div>
                <div v-if="itemDiscount > 0" class="flex justify-between py-1"><span class="text-slate-500">Diskon item</span><span>− {{ money(itemDiscount) }}</span></div>
                <div v-if="hasDiscount" class="flex justify-between py-1"><span class="text-slate-500">Diskon</span><span>− {{ money(sale.discount) }}</span></div>
                <div v-if="Number(sale.header_discount) > 0" class="flex justify-between py-1"><span class="text-slate-500">Diskon khusus</span><span>− {{ money(sale.header_discount) }}</span></div>
                <div class="mt-1 flex justify-between border-t-[1.5px] border-slate-900 pt-2 text-[13px] font-bold text-slate-900">
                    <span>TOTAL</span><span>{{ money(sale.total) }}</span>
                </div>
            </div>

            <p v-if="sale.notes" class="mt-4 rounded bg-slate-50 px-3 py-2 text-[9px] whitespace-pre-line text-slate-600">
                Catatan: {{ sale.notes }}
            </p>

            <p class="mt-6 border-t border-dashed border-slate-300 pt-3 text-center text-[9px] text-slate-500">
                {{ store.footer || 'Terima kasih' }}
            </p>
            <p class="mt-2 text-center text-[7.5px] text-slate-400 leading-relaxed">
                Garansi berlaku selama tidak ada indikasi Human Error / Kesalahan Pengguna.<br>
                Tidak ada pengembalian barang / uang untuk barang yang sudah dibeli.
            </p>
        </div>

        <template #footer>
            <button type="button" @click="emit('close')"
                class="px-4 py-2.5 text-sm font-medium rounded-lg transition-colors text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700">
                Batal
            </button>
            <button type="button" :disabled="!phone || sending" @click="sendWa"
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