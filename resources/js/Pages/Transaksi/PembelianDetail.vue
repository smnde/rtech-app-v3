<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ChevronRight, Printer, User } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import ReceiptModal from '@/Components/Cart/ReceiptModal.vue';
import { usePermission } from '@/composables/usePermission';

const { can } = usePermission();

const props = defineProps({
    purchase: { type: Object, required: true },
});

const statusColor = { lunas: 'green', hutang: 'yellow' };
const orderStatusColor = { ordered: 'blue', pending: 'yellow', done: 'green' };
const orderStatusLabel = { ordered: 'Ordered', pending: 'Pending', done: 'Done' };

const markDone = () => {
    if (!confirm('Tandai pembelian sebagai Done? Stok akan masuk & mutasi dicatat saat ini.')) return;
    router.post(route('transaksi.pembelian.done', props.purchase.id), {}, {
        preserveScroll: true,
    });
};

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

const specEntries = (spec) => Object.entries(spec ?? {}).filter(([, v]) => v);

const source = (purchase) => purchase.supplier ?? purchase.customer;

const receiptMessage = computed(() => {
    const p = props.purchase;
    const lines = [
        `*NOTA PEMBELIAN*`,
        p.invoice,
        formatDateTime(p.date),
        '',
        ...p.items.map((item) => `${item.item_name ?? '-'} ${item.qty} x ${rupiah(item.price)} = ${rupiah(item.subtotal)}`),
        '',
        `Total: ${rupiah(p.total)}`,
        `Status: ${p.payment_status === 'lunas' ? 'LUNAS' : 'HUTANG'}`,
    ];
    return lines.join('\n');
});

const waPhone = computed(() => props.purchase.customer?.phone ?? props.purchase.supplier?.phone ?? '');

const receiptOpen = ref(false);
const store = computed(() => usePage().props.store ?? {});

const receiptSale = computed(() => ({
    invoice_no: props.purchase.invoice,
    created_at: props.purchase.date,
    user: props.purchase.cashier ? { name: props.purchase.cashier } : null,
    supplier: props.purchase.supplier?.name ?? null,
    supplier_phone: props.purchase.supplier?.phone ?? null,
    customer: props.purchase.customer ?? null,
    buy_from_reference: props.purchase.buy_from_reference,
    payment_status: props.purchase.payment_status,
    paid_at: props.purchase.paid_at,
    subtotal: props.purchase.total,
    total: props.purchase.total,
    notes: props.purchase.notes,
    items: props.purchase.items.map((item) => ({
        item_name: item.item_name,
        item_code: item.item_code,
        serial_number: item.serial_number,
        specification: item.specification,
        quantity: item.qty,
        price: item.price,
        subtotal: item.subtotal,
    })),
}));

const cardClass = 'rounded-xl p-4 border transition-colors bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800';
const sectionTitle = 'text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3';
const labelClass = 'text-xs text-gray-500 dark:text-gray-500 mb-1';
</script>

<template>
    <Head :title="`Pembelian ${purchase.invoice}`" />

    <AppLayout title="Detail Pembelian">
        <div class="flex-1 min-h-0 flex flex-col gap-4">

            <div class="shrink-0 flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('transaksi.pembelian')"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke daftar pembelian
                </Link>
                <div class="flex items-center gap-2">
                    <AppButton variant="outline" size="sm" @click="receiptOpen = true">
                        <Printer class="h-4 w-4" />
                        Cetak Nota
                    </AppButton>
                    <AppButton
                        v-if="purchase.status !== 'done' && purchase.supplier && can('edit purchase')"
                        variant="primary"
                        size="sm"
                        @click="markDone"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Tandai Done
                    </AppButton>
                </div>
            </div>

            <div class="scrollbar-styled scrollbar-mobile-hidden flex-1 min-h-0 overflow-auto flex flex-col gap-4">

                <div :class="cardClass" class="w-full">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="font-mono text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ purchase.invoice }}</p>
                        <div class="flex items-center gap-1.5">
                            <AppBadge :color="orderStatusColor[purchase.status] ?? 'gray'" size="sm">{{ orderStatusLabel[purchase.status] ?? purchase.status }}</AppBadge>
                            <AppBadge :color="statusColor[purchase.payment_status] ?? 'gray'" size="sm" dot>{{ purchase.payment_status }}</AppBadge>
                        </div>
                    </div>

                    <h2 :class="sectionTitle">Informasi Umum</h2>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="col-span-2">
                            <p :class="labelClass">{{ purchase.supplier ? 'Supplier' : 'Customer' }}</p>
                            <template v-if="source(purchase)">
                                <Link v-if="purchase.supplier === null"
                                    :href="route('customers.show', purchase.customer.id)"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">
                                    <User class="h-4 w-4" />
                                    {{ purchase.customer.name }}
                                </Link>
                                <p v-else class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                    <User class="h-4 w-4" />
                                    {{ purchase.supplier.name }}
                                </p>
                                <p class="mt-0.5 text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ source(purchase).phone ?? '—' }}</p>
                            </template>
                            <p v-else class="text-sm text-gray-900 dark:text-white">Tanpa sumber</p>
                        </div>
                        <div>
                            <p :class="labelClass">Tanggal</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ formatDateTime(purchase.date) }}</p>
                        </div>
                        <div>
                            <p :class="labelClass">Kasir</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ purchase.cashier ?? '—' }}</p>
                        </div>
                        <div v-if="purchase.buy_from_reference">
                            <p :class="labelClass">No. Nota Supplier</p>
                            <p class="font-mono text-sm text-gray-900 dark:text-white">{{ purchase.buy_from_reference }}</p>
                        </div>
                        <div v-if="purchase.payment_status === 'hutang' && purchase.paid_at">
                            <p :class="labelClass">Dibayar</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ formatDateTime(purchase.paid_at) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="col-span-2 sm:col-span-4 rounded-lg bg-indigo-50 p-3 dark:bg-indigo-500/10">
                            <p :class="labelClass">Total</p>
                            <p class="text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ rupiah(purchase.total) }}</p>
                        </div>
                    </div>

                    <p v-if="purchase.notes" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-line text-gray-600 dark:bg-gray-800/50 dark:text-gray-300">
                        {{ purchase.notes }}
                    </p>
                </div>

                <div :class="cardClass" class="w-full">
                    <h2 :class="sectionTitle">Barang</h2>

                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="(item, index) in purchase.items" :key="index" class="py-2.5">
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
            title="NOTA PEMBELIAN" @close="receiptOpen = false" />
    </AppLayout>
</template>
