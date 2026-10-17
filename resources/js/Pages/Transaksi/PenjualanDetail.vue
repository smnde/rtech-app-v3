<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ChevronRight, Printer, Trash2, User } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppConfirmModal from '@/Components/UI/AppConfirmModal.vue';
import { usePermission } from '@/composables/usePermission';
import { useConfirm } from '@/composables/useConfirm';
import ReceiptModal from '@/Components/Cart/ReceiptModal.vue';

const { can } = usePermission();
const confirm = useConfirm();

const markPaid = () => {
    if (!confirm('Tandai penjualan sebagai Lunas?')) return;
    router.post(route('transaksi.penjualan.lunas', props.sale.id), {}, {
        preserveScroll: true,
    });
};

const deleteSale = async () => {
    const ok = await confirm.confirm({
        title: 'Hapus penjualan?',
        message: 'Stok akan dikembalikan & mutasi terkait dihapus. Tindakan ini permanen.',
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('transaksi.penjualan.destroy', props.sale.id), {
        preserveScroll: true,
    });
};

const props = defineProps({
    sale: { type: Object, required: true },
});

const store = computed(() => usePage().props.store ?? {});
const receiptOpen = ref(new URLSearchParams(window.location.search).has('print'));

const statusColor = { lunas: 'green', hutang: 'yellow', keep: 'purple' };
const paymentMethodLabel = { cash: 'Cash', tf: 'Transfer' };

const rupiah = (value) => (value === null || value === undefined
    ? '—'
    : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value));

const formatDateTime = (value) => (value
    ? new Date(value.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const expandedItems = ref(new Set());
const toggleItem = (index) => {
    const next = new Set(expandedItems.value);
    next.has(index) ? next.delete(index) : next.add(index);
    expandedItems.value = next;
};

const hasSpec = (item) => !!(item.specification || item.serial_number || item.accessories?.length || item.features?.length);

const itemDiscount = computed(() => props.sale.items.reduce((sum, item) => sum + (item.discount || 0), 0));

const specEntries = (spec) => Object.entries(spec ?? {}).filter(([, v]) => v);

const cardClass = 'rounded-xl p-4 border transition-colors bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800';
const sectionTitle = 'text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3';
const labelClass = 'text-xs text-gray-500 dark:text-gray-500 mb-1';

const receiptSale = computed(() => ({
    invoice_no: props.sale.invoice,
    created_at: props.sale.date,
    user: props.sale.cashier ? { name: props.sale.cashier } : null,
    customer: props.sale.customer,
    payment_status: props.sale.payment_status,
    payment_method: paymentMethodLabel[props.sale.payment_method] ?? props.sale.payment_method,
    subtotal: props.sale.subtotal,
    discount: props.sale.discount,
    header_discount: props.sale.header_discount,
    dp_amount: props.sale.dp_amount,
    paid_at: props.sale.paid_at,
    total: props.sale.total,
    notes: props.sale.notes,
    items: props.sale.items.map((item) => ({
        item_name: item.item_name,
        item_code: item.item_code,
        serial_number: item.serial_number,
        specification: item.specification,
        quantity: item.qty,
        price: item.price,
        discount: item.discount,
        subtotal: item.subtotal,
    })),
}));

const receiptMessage = computed(() => {
    const s = props.sale;
    const storeName = store.value.name ?? 'Toko';
    const lines = [
        `*${storeName}*`,
        '',
        `*NOTA PENJUALAN* ${s.invoice}`,
        formatDateTime(s.date),
        `Customer: ${s.customer?.name ?? 'Umum'}`,
        `Kasir: ${s.cashier ?? '-'}`,
        '',
        ...s.items.map((item) => `${item.item_name ?? '-'} ${item.qty} x ${rupiah(item.price)} = ${rupiah(item.subtotal)}`),
        '',
        `Subtotal: ${rupiah(s.subtotal)}`,
    ];

    if (Number(s.header_discount) > 0) lines.push(`Diskon: - ${rupiah(s.header_discount)}`);
    if (Number(itemDiscount.value) > 0) lines.push(`Diskon item: - ${rupiah(itemDiscount.value)}`);

    lines.push(
        `Total: ${rupiah(s.total)}`,
        `Status: ${s.payment_status === 'lunas' ? 'LUNAS' : 'BELUM LUNAS'}`,
        '',
        store.value.footer ?? 'Terima kasih',
    );

    return lines.join('\n');
});

const waPhone = computed(() => props.sale.customer?.phone ?? '');
</script>

<template>
    <Head :title="`Penjualan ${sale.invoice}`" />

    <AppLayout title="Detail Penjualan">
        <div class="flex-1 min-h-0 flex flex-col gap-4">

            <div class="shrink-0 flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('transaksi.penjualan')"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke daftar penjualan
                </Link>
                <div class="flex items-center gap-2">
                    <AppButton
                        v-if="['keep', 'hutang'].includes(sale.payment_status) && can('edit sale')"
                        variant="primary" size="sm" @click="markPaid"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Tandai Lunas
                    </AppButton>
                    <AppButton variant="primary" size="sm" @click="receiptOpen = true">
                        <Printer class="h-4 w-4" />
                        Cetak Nota
                    </AppButton>
                    <AppButton v-if="can('delete sale')" variant="danger" size="sm" @click="deleteSale">
                        <Trash2 class="h-4 w-4" />
                        Hapus
                    </AppButton>
                </div>
            </div>

            <div class="scrollbar-styled scrollbar-mobile-hidden flex-1 min-h-0 overflow-auto flex flex-col gap-4">

                <div :class="cardClass" class="w-full">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="font-mono text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ sale.invoice }}</p>
                        <AppBadge :color="statusColor[sale.payment_status] ?? 'gray'" size="sm" dot>{{ sale.payment_status }}</AppBadge>
                    </div>

                    <h2 :class="sectionTitle">Informasi Umum</h2>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="col-span-2">
                            <p :class="labelClass">Customer</p>
                            <template v-if="sale.customer">
                                <Link :href="route('customers.show', sale.customer.id)"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">
                                    <User class="h-4 w-4" />
                                    {{ sale.customer.name }}
                                </Link>
                                <p class="mt-0.5 text-sm tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ sale.customer.phone ?? '—' }}
                                    <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="sale.customer.is_member
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'">
                                        {{ sale.customer.is_member ? 'Member' : 'Non-member' }}
                                    </span>
                                </p>
                            </template>
                            <p v-else class="text-sm text-gray-900 dark:text-white">Tanpa customer</p>
                        </div>
                        <div>
                            <p :class="labelClass">Tanggal</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ formatDateTime(sale.date) }}</p>
                        </div>
                        <div>
                            <p :class="labelClass">Kasir</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ sale.cashier ?? '—' }}</p>
                        </div>
                        <div>
                            <p :class="labelClass">Pembayaran</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ paymentMethodLabel[sale.payment_method] ?? '—' }}</p>
                        </div>
                        <div v-if="sale.payment_status === 'keep'">
                            <p :class="labelClass">Dibayar</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ formatDateTime(sale.paid_at) }}</p>
                        </div>
                        <div v-if="sale.payment_status === 'keep'">
                            <p :class="labelClass">DP</p>
                            <p class="text-sm tabular-nums text-gray-900 dark:text-white">{{ rupiah(sale.dp_amount) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <p :class="labelClass">Subtotal</p>
                            <p class="text-sm tabular-nums text-gray-900 dark:text-white">{{ rupiah(sale.subtotal) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <p :class="labelClass">Diskon item</p>
                            <p :class="['text-sm tabular-nums', itemDiscount ? 'text-red-500' : 'text-gray-900 dark:text-white']">
                                {{ itemDiscount ? `- ${rupiah(itemDiscount)}` : '-' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <p :class="labelClass">Diskon khusus</p>
                            <p :class="['text-sm tabular-nums', sale.header_discount ? 'text-red-500' : 'text-gray-900 dark:text-white']">
                                {{ sale.header_discount ? `- ${rupiah(sale.header_discount)}` : '-' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-indigo-50 p-3 dark:bg-indigo-500/10">
                            <p :class="labelClass">Total</p>
                            <p class="text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ rupiah(sale.total) }}</p>
                        </div>
                    </div>

                    <p v-if="sale.notes" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-line text-gray-600 dark:bg-gray-800/50 dark:text-gray-300">
                        {{ sale.notes }}
                    </p>
                </div>

                <div :class="cardClass" class="w-full">
                    <h2 :class="sectionTitle">Barang</h2>

                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="(item, index) in sale.items" :key="index" class="py-2.5">
                            <button type="button"
                                class="flex w-full items-start justify-between gap-3 text-left"
                                :class="hasSpec(item) ? 'cursor-pointer' : 'cursor-default'"
                                :disabled="!hasSpec(item)"
                                @click="toggleItem(index)">
                                <div class="flex min-w-0 items-start gap-2">
                                    <ChevronRight v-if="hasSpec(item)"
                                        class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                        :class="expandedItems.has(index) ? 'rotate-90' : ''" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.item_name ?? '—' }}</p>
                                        <p class="font-mono text-xs text-gray-500">{{ item.item_code }} · {{ item.qty }} × {{ rupiah(item.price) }}</p>
                                        <p v-if="item.discount" class="text-xs tabular-nums text-red-500">diskon - {{ rupiah(item.discount) }}</p>
                                    </div>
                                </div>
                                <p class="shrink-0 text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ rupiah(item.subtotal) }}</p>
                            </button>

                            <div v-if="hasSpec(item) && expandedItems.has(index)"
                                class="mt-2 ml-6 space-y-2 rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800/50">
                                <div v-if="item.serial_number">
                                    <p :class="labelClass">Serial Number</p>
                                    <p class="font-mono text-gray-700 dark:text-gray-300">{{ item.serial_number }}</p>
                                </div>
                                <div v-if="specEntries(item.specification).length">
                                    <p :class="labelClass">Spesifikasi</p>
                                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-gray-700 dark:text-gray-300">
                                        <template v-for="[key, value] in specEntries(item.specification)" :key="key">
                                            <dt class="text-gray-500 capitalize">{{ key }}</dt>
                                            <dd>{{ value }}</dd>
                                        </template>
                                    </dl>
                                </div>
                                <div v-if="item.accessories?.length">
                                    <p :class="labelClass">Aksesoris</p>
                                    <p class="text-gray-700 dark:text-gray-300">{{ item.accessories.join(', ') }}</p>
                                </div>
                                <div v-if="item.features?.length">
                                    <p :class="labelClass">Fitur</p>
                                    <p class="text-gray-700 dark:text-gray-300">{{ item.features.join(', ') }}</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <ReceiptModal :show="receiptOpen" :store="store" :sale="receiptSale"
            :phone="waPhone" :wa-text="receiptMessage"
            title="NOTA PENJUALAN" @close="receiptOpen = false" />

        <AppConfirmModal />
    </AppLayout>
</template>
