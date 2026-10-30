<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, Trash2, Search, ShoppingBag,
    Receipt, Save, CreditCard, UserPlus, X, ChevronDown,
    CheckCircle2, Clock, Package, Banknote, Gift,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppNumberInput from '@/Components/UI/AppNumberInput.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import CustomerFormModal from '@/Components/Customers/CustomerFormModal.vue';
const props = defineProps({
    products: { type: Array, default: () => [] },
    cashiers: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    cart: { type: Object, required: true },
    heldCarts: { type: Array, default: () => [] },
});

const page = usePage();

// ─── Header transaksi ─────────────────────────────────────────
// Tanpa PO/Non-PO dan tanpa No. Invoice (invoice dibuat otomatis oleh BE).
const form = ref({
    date: new Date().toISOString().slice(0, 10),
    user_id: '',               // kasir yang dipilih (wajib)
    status: 'lunas',           // lunas | hutang | keep
    payment: 'cash',           // cash | tf
    paid_amount: '',
    due_date: '',              // jatuh tempo — opsional, hanya untuk hutang/keep
    note: '',
    discount_type: 'nominal',  // nominal | percent
    discount_value: '',
});

// Status pembayaran — dipilih lewat tab, bukan dropdown.
// lunas  : dibayar penuh.
// hutang : belum dibayar sama sekali.
// keep   : pembayaran sebagian / menyusul, stok tetap langsung berkurang.
const STATUS_TABS = [
    { id: 'lunas',  label: 'Lunas',  icon: CheckCircle2 },
    { id: 'hutang', label: 'Hutang', icon: Clock },
    { id: 'keep',   label: 'Keep',   icon: Package },
];

/** Hutang & Keep butuh input nominal bayar + jatuh tempo. */
const isPartial = computed(() => form.value.status === 'hutang' || form.value.status === 'keep');

// Metode pembayaran — juga tab, bukan dropdown.
const PAYMENT_TABS = [
    { id: 'cash', label: 'Cash', icon: Banknote },
    { id: 'tf',   label: 'Transfer', icon: CreditCard },
];

// Rincian item di panel checkout bisa dilipat supaya panel tidak memanjang.
const detailsOpen = ref(true);

// ─── Customer dari DB (bisa nambah dari modal) ────────────────
const customers = ref(props.customers.map((c) => ({ ...c })));

// ─── Kasir (wajib dipilih, disimpan ke sales.user_id) ─────────
const cashierOptions = computed(() => props.cashiers.map((u) => ({ id: u.id, label: u.name })));

// ─── Customer terpilih + picker ───────────────────────────────
const selectedCustomer = computed(() =>
    customers.value.find((c) => c.id === props.cart.customer_id) ?? null,
);
const customerSearch    = ref('');
const customerOpen      = ref(false);

const customerResults = computed(() => {
    const q = customerSearch.value.trim().toLowerCase();
    if (!q) return customers.value;
    return customers.value.filter((c) =>
        c.name.toLowerCase().includes(q) || (c.phone ?? '').includes(q),
    );
});

const hasNoCustomerMatch = computed(() => {
    const q = customerSearch.value.trim();
    return q.length > 0 && customerResults.value.length === 0;
});

const selectCustomer = (customer) => {
    customerSearch.value = '';
    customerOpen.value = false;
    errors.value.customer_id = '';
    router.put(route('transaksi.keranjang.customer', 'sale'), { customer_id: customer.id }, { preserveScroll: true });
};

const clearCustomer = () => {
    router.put(route('transaksi.keranjang.customer', 'sale'), { customer_id: null }, { preserveScroll: true });
};

// ─── Modal tambah customer baru ───────────────────────────────
const showCustomerModal = ref(false);
const customerModalRef  = ref(null);

const openCustomerModal = () => {
    customerOpen.value = false;
    showCustomerModal.value = true;
};

const handleCustomerSubmit = (payload) => {
    router.post(route('customers.store'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            const created = page.props.flash?.customer;
            if (created) {
                customers.value.push(created);
                selectCustomer(created);
            }
            showCustomerModal.value = false;
        },
        onFinish: () => customerModalRef.value?.stopLoading(),
    });
};

// ─── Tab mobile (pola dari backup) ────────────────────────────
const activeTab = ref('cari');   // cari | keranjang | checkout

// Deteksi mobile — dipakai untuk auto-pindah tab. Cukup lebar window.
const isMobile = ref(window.innerWidth < 1024);
const syncIsMobile = () => { isMobile.value = window.innerWidth < 1024; };

onMounted(() => window.addEventListener('resize', syncIsMobile));
onUnmounted(() => window.removeEventListener('resize', syncIsMobile));

// ─── Pencarian & pemilihan produk ─────────────────────────────
const productSearch = ref('');

// ─── Keranjang (server-side: tersimpan di tabel carts / cart_items) ─
const expandedItems = ref(new Set());
const items = computed(() => props.cart.items.map((line) => ({ ...line, expanded: expandedItems.value.has(line.id) })));

const toggleExpand = (line) => {
    const next = new Set(expandedItems.value);
    next.has(line.id) ? next.delete(line.id) : next.add(line.id);
    expandedItems.value = next;
};

const lineKey = (line) => `${line.item_type}-${line.item_id}`;

const searchResults = computed(() => {
    const q = productSearch.value.trim().toLowerCase();
    if (!q) return [];
    const inCart = new Set(items.value.map(lineKey));

    return props.products
        .filter((p) =>
            p.label.toLowerCase().includes(q)
            || p.sku.toLowerCase().includes(q)
            || (p.code ?? '').toLowerCase().includes(q),
        )
        .map((p) => ({ ...p, inCart: inCart.has(lineKey(p)) }));
});

const addToCart = (product) => {
    router.post(route('transaksi.keranjang.item.store', 'sale'), {
        item_type: product.item_type,
        item_id: product.id,
    }, {
        preserveScroll: true,
        onSuccess: () => { productSearch.value = ''; },
    });

    // Di mobile, langsung pindah ke tab keranjang biar user lihat hasilnya
    if (isMobile.value) activeTab.value = 'keranjang';
};

const removeItem = (line) => {
    router.delete(route('transaksi.keranjang.item.destroy', line.id), { preserveScroll: true });
};

const updateItem = (line, data) => {
    router.put(route('transaksi.keranjang.item.update', line.id), data, { preserveScroll: true, preserveState: true });
};

const stepQty = (line, delta) => {
    updateItem(line, { qty: Math.max(1, Number(line.qty || 1) + delta) });
};

/**
 * Jadikan item sebagai bonus: harga jadi 0 dan diskonnya direset.
 * Sekali ditekan, tombolnya hilang (satu arah) — batalkan dengan hapus itemnya.
 */
const markAsBonus = (line) => {
    updateItem(line, { is_bonus: true, price: 0, discount: 0 });
};

const holdLabel = ref('');

const holdCart = () => {
    router.post(route('transaksi.keranjang.hold', 'sale'), { label: holdLabel.value || null }, {
        preserveScroll: true,
        onSuccess: () => { holdLabel.value = ''; },
    });
};

const resumeCart = (held) => {
    router.post(route('transaksi.keranjang.resume', held.id), {}, { preserveScroll: true });
};

const discardHeld = (held) => {
    router.delete(route('transaksi.keranjang.destroy', held.id), { preserveScroll: true });
};

// ─── Perhitungan ──────────────────────────────────────────────
const subtotal = computed(() =>
    items.value.reduce((sum, line) => sum + Number(line.qty || 0) * Number(line.price || 0), 0),
);

const lineDiscounts = computed(() =>
    items.value.reduce((sum, line) => sum + Number(line.discount || 0), 0),
);

const headerDiscount = computed(() => {
    const v = Number(form.value.discount_value || 0);
    if (!v) return 0;
    return form.value.discount_type === 'percent'
        ? Math.round((subtotal.value * v) / 100)
        : v;
});

const total = computed(() =>
    Math.max(0, subtotal.value - lineDiscounts.value - headerDiscount.value),
);

const totalQty = computed(() => items.value.reduce((s, l) => s + Number(l.qty || 0), 0));

const formatRupiah = (v) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;

// ─── Validasi & simpan ────────────────────────────────────────
const errors = ref({});
const saving = ref(false);

const validate = () => {
    const e = {};
    if (!form.value.date) e.date = 'Tanggal wajib diisi.';
    if (!form.value.user_id) e.user_id = 'Kasir wajib dipilih.';
    if (!selectedCustomer.value) e.customer_id = 'Customer wajib dipilih.';
    if (items.value.length === 0) e.items = 'Keranjang masih kosong.';
    if (items.value.some((l) => Number(l.qty || 0) < 1)) e.items = 'Jumlah minimal 1.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

/** Sisa yang belum dibayar — hanya berarti untuk DP / Hutang. */
const remaining = computed(() => {
    if (!isPartial.value) return 0;
    return Math.max(0, total.value - Number(form.value.paid_amount || 0));
});

/**
 * Syarat transaksi boleh disimpan: customer terpilih, keranjang tidak kosong,
 * dan semua qty minimal 1. Dipakai untuk menonaktifkan tombol simpan.
 */
const canSave = computed(() =>
    !!form.value.user_id
    && !!selectedCustomer.value
    && items.value.length > 0
    && !items.value.some((l) => Number(l.qty || 0) < 1),
);

const handleSave = () => {
    if (!validate()) return;

    router.post(route('transaksi.penjualan.store'), {
        date: form.value.date,
        user_id: form.value.user_id,
        customer_id: props.cart.customer_id ?? null,
        payment_status: form.value.status,
        payment_method: form.value.payment,
        dp_amount: form.value.status === 'keep' ? Number(form.value.paid_amount || 0) : null,
        paid_at: isPartial.value ? (form.value.due_date || null) : null,
        notes: form.value.note || null,
        discount: headerDiscount.value,
        items: items.value.map((l) => ({
            item_type: l.item_type,
            item_id: l.item_id,
            qty: Number(l.qty),
            price: Number(l.price),
            discount: Number(l.discount || 0),
            is_bonus: !!l.is_bonus,
        })),
    }, {
        preserveScroll: true,
        onStart: () => { saving.value = true; },
        onFinish: () => { saving.value = false; },
    });
};

const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError
        ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
        : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];
</script>

<template>
    <Head title="Tambah Penjualan" />

    <AppLayout title="Tambah Penjualan">
        <!-- Scroll hanya di dalam card, bukan sehalaman — pola sama dengan halaman list. -->
        <div class="flex h-full flex-col">
        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TOOLBAR: kembali + judul                            -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="mb-4 flex shrink-0 items-center gap-3">
            <Link :href="route('transaksi.penjualan')"
                class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                aria-label="Kembali">
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div class="min-w-0">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Penjualan Baru</h1>
                <p class="hidden sm:block text-sm text-gray-500 dark:text-gray-400">
                    Pilih produk, atur jumlah & diskon, lalu simpan.
                </p>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- MOBILE: 3 tab (Cari / Keranjang / Checkout)         -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="flex-1 min-h-0 scrollbar-styled overflow-y-auto lg:hidden">
            <!-- Tab bar -->
            <div class="mb-4 flex shrink-0 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                <button
                    v-for="tab in [
                        { key: 'cari',      label: 'Cari Produk', icon: Search },
                        { key: 'keranjang', label: 'Keranjang',   icon: ShoppingBag },
                        { key: 'checkout',  label: 'Checkout',    icon: CreditCard },
                    ]"
                    :key="tab.key"
                    type="button"
                    @click="activeTab = tab.key"
                    :class="[
                        'relative flex flex-1 items-center justify-center gap-1.5 py-2.5 text-xs font-semibold transition-colors',
                        activeTab === tab.key
                            ? 'bg-primary-600 text-white'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white',
                    ]"
                >
                    <component :is="tab.icon" class="h-3.5 w-3.5" />
                    {{ tab.label }}
                    <span
                        v-if="tab.key === 'keranjang' && items.length"
                        :class="[
                            'min-w-[1.1rem] h-[1.1rem] rounded-full text-[10px] font-bold flex items-center justify-center px-1',
                            activeTab === 'keranjang' ? 'bg-white text-primary-600' : 'bg-primary-600 text-white',
                        ]"
                    >
                        {{ items.length }}
                    </span>
                </button>
            </div>

            <!-- ── Tab: Cari Produk ── -->
            <div v-show="activeTab === 'cari'" class="space-y-3">
                <AppCard>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="productSearch"
                            type="text"
                            placeholder="Cari produk berdasarkan nama atau SKU..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                </AppCard>

                <div class="space-y-2">
                    <div
                        v-for="product in searchResults"
                        :key="product.id"
                        class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ product.label }}</p>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[10px] font-mono text-gray-500 dark:text-gray-400">{{ product.sku }}</span>
                                <span v-if="product.code" class="rounded-full bg-orange-100 dark:bg-orange-900/40 px-2 py-0.5 text-[10px] font-mono font-medium text-orange-700 dark:text-orange-300">{{ product.code }}</span>
                                <span class="text-xs text-gray-400 tabular-nums">{{ formatRupiah(product.sell_price) }}</span>
                            </div>
                            <p v-if="product.spec" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ product.spec }}</p>
                        </div>
                        <AppBadge v-if="product.inCart" color="green" size="sm">Di keranjang</AppBadge>
                        <AppButton variant="outline" size="sm" @click="addToCart(product)">
                            <Plus class="h-4 w-4" />
                            Tambah
                        </AppButton>
                    </div>

                    <div v-if="searchResults.length === 0" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="productSearch.trim()">
                            Tidak ada produk cocok.
                        </template>
                        <template v-else>
                            Mulai ketik untuk mencari produk.
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── Tab: Keranjang ── -->
            <div v-show="activeTab === 'keranjang'" class="space-y-3">
                <div v-if="items.length === 0" class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-16 text-center">
                    <ShoppingBag class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-700" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">Keranjang kosong</p>
                    <button type="button" class="mt-3 text-sm font-medium text-primary-600 dark:text-primary-400"
                        @click="activeTab = 'cari'">
                        + Cari produk
                    </button>
                </div>

                <template v-else>
                    <div
                        v-for="line in items"
                        :key="lineKey(line)"
                        class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <!-- Nama item = tombol expand (buka spek) -->
                            <button type="button" @click="toggleExpand(line)"
                                class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ line.name }}</p>
                                        <AppBadge v-if="line.is_bonus" color="green" size="sm">FREE</AppBadge>
                                    </div>
                                    <p class="text-xs font-semibold text-green-600 dark:text-green-400 tabular-nums">
                                        {{ line.is_bonus ? 'Gratis' : formatRupiah(line.qty * line.price) }}
                                    </p>
                                </div>
                                <ChevronDown class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                    :class="line.expanded ? 'rotate-180' : ''" />
                            </button>

                            <!-- Qty stepper -->
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" @click="stepQty(line, -1)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                    −
                                </button>
                                <span class="w-8 text-center text-sm font-semibold text-gray-900 dark:text-white tabular-nums">{{ line.qty }}</span>
                                <button type="button" @click="stepQty(line, 1)"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                    +
                                </button>
                            </div>

                            <!-- Bonus: hilang setelah ditekan -->
                            <button v-if="!line.is_bonus" type="button" @click="markAsBonus(line)"
                                title="Jadikan bonus"
                                class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-400/10 transition-colors">
                                <Gift class="h-4 w-4" />
                            </button>

                            <button type="button" @click="removeItem(line)"
                                class="shrink-0 rounded-lg p-1.5 text-gray-300 dark:text-gray-600 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-400/10 transition-colors">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Spek (muncul saat di-expand) -->
                        <div v-show="line.expanded" class="mt-2.5 space-y-1 rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3 py-2.5">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="text-gray-400">Kode</span>
                                <span class="font-mono text-gray-600 dark:text-gray-300">{{ line.code }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <span class="shrink-0 text-gray-400">Spek</span>
                                <span class="text-right text-gray-600 dark:text-gray-300">{{ line.spec || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="text-gray-400">Harga</span>
                                <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ formatRupiah(line.price) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ totalQty }} item</span>
                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ formatRupiah(subtotal) }}</span>
                        </div>
                    </div>

                    <AppButton variant="primary" size="md" class="w-full" @click="activeTab = 'checkout'">
                        Lanjut Checkout
                    </AppButton>
                </template>
            </div>

            <!-- ── Tab: Checkout ── -->
            <div v-show="activeTab === 'checkout'" class="pb-4 space-y-3">
                <AppCard>
                    <div class="grid grid-cols-1 gap-4">
                        <!-- Tanggal -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Tanggal <span class="text-red-500">*</span>
                            </label>
                            <AppDatePicker v-model="form.date" :error="!!errors.date" />
                            <p v-if="errors.date" class="mt-1.5 text-xs text-red-500">{{ errors.date }}</p>
                        </div>

                        <!-- Kasir -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Kasir <span class="text-red-500">*</span>
                            </label>
                            <AppSelect v-model="form.user_id" :options="cashierOptions" placeholder="Pilih kasir..." :error="!!errors.user_id" />
                            <p v-if="errors.user_id" class="mt-1.5 text-xs text-red-500">{{ errors.user_id }}</p>
                        </div>

                        <!-- Customer -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Customer <span class="text-red-500">*</span>
                            </label>

                            <!-- Terpilih: card kecil bisa di-x -->
                            <div v-if="selectedCustomer"
                                class="flex items-center gap-3 rounded-lg border border-green-500/50 dark:border-green-500/40 bg-gray-50 dark:bg-gray-800 px-3 py-2.5">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 font-semibold">
                                    {{ selectedCustomer.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ selectedCustomer.name }}</p>
                                        <AppBadge v-if="selectedCustomer.is_member" color="yellow" size="sm">★ Member</AppBadge>
                                    </div>
                                    <p v-if="selectedCustomer.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCustomer.phone }}</p>
                                </div>
                                <button type="button" @click="clearCustomer"
                                    class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                    aria-label="Hapus customer">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- Belum dipilih: search + tombol tambah baru -->
                            <div v-else class="relative">
                                <input
                                    v-model="customerSearch"
                                    type="text"
                                    placeholder="Cari nama atau nomor telepon..."
                                    @focus="customerOpen = true"
                                    @blur="() => setTimeout(() => customerOpen = false, 150)"
                                    :class="inputClass(!!errors.customer_id)"
                                />

                                <div v-if="customerOpen && customerSearch.trim()"
                                    class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl max-h-64 overflow-y-auto">
                                    <button v-for="c in customerResults" :key="c.id" type="button"
                                        @mousedown.prevent @click="selectCustomer(c)"
                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-sm font-semibold">
                                            {{ c.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ c.name }}</p>
                                                <AppBadge v-if="c.is_member" color="yellow" size="sm">★ Member</AppBadge>
                                            </div>
                                            <p v-if="c.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ c.phone }}</p>
                                        </div>
                                    </button>

                                    <!-- Tidak ada hasil → tombol tambah baru -->
                                    <button v-if="hasNoCustomerMatch" type="button"
                                        @mousedown.prevent @click="openCustomerModal"
                                        class="flex w-full items-center gap-2 border-t border-gray-100 dark:border-gray-700 px-3 py-2.5 text-left text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                        <UserPlus class="h-4 w-4 shrink-0" />
                                        <span class="text-sm">Tambah customer baru: "<span class="font-medium">{{ customerSearch.trim() }}</span>"</span>
                                    </button>
                                </div>
                            </div>

                            <p v-if="errors.customer_id" class="mt-1.5 text-xs text-red-500">{{ errors.customer_id }}</p>
                        </div>

                        <!-- Status — tab, bukan dropdown -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                            <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800/60">
                                <button
                                    v-for="opt in STATUS_TABS"
                                    :key="opt.id"
                                    type="button"
                                    @click="form.status = opt.id"
                                    :class="[
                                        'inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-2 text-sm font-medium transition-colors',
                                        form.status === opt.id
                                            ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                            : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    <component :is="opt.icon" class="h-4 w-4 shrink-0" />
                                    <span class="truncate">{{ opt.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Metode bayar — tab juga -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Metode Bayar</label>
                            <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800/60">
                                <button
                                    v-for="opt in PAYMENT_TABS"
                                    :key="opt.id"
                                    type="button"
                                    @click="form.payment = opt.id"
                                    :class="[
                                        'inline-flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                        form.payment === opt.id
                                            ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                            : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    <component :is="opt.icon" class="h-4 w-4" />
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Nominal bayar + jatuh tempo (khusus Hutang / Keep) -->
                        <template v-if="isPartial">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Jumlah Bayar <span class="text-xs font-normal text-gray-400">(opsional)</span>
                                </label>
                                <AppCurrencyInput v-model="form.paid_amount" placeholder="0" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Jatuh Tempo <span class="text-xs font-normal text-gray-400">(opsional)</span>
                                </label>
                                <AppDatePicker v-model="form.due_date" placeholder="Pilih tanggal jatuh tempo..." />
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3.5 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Sisa Hutang</span>
                                <span class="text-sm font-semibold text-red-500">{{ formatRupiah(remaining) }}</span>
                            </div>
                        </template>
                    </div>
                </AppCard>

                <!-- Rincian + diskon (bisa dilipat) -->
                <AppCard>
                    <button type="button" @click="detailsOpen = !detailsOpen"
                        class="flex w-full items-center justify-between gap-2 text-left">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Rincian</h2>
                        <ChevronDown class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                            :class="detailsOpen ? 'rotate-180' : ''" />
                    </button>

                    <div v-show="detailsOpen" class="mt-3 space-y-3">
                        <div v-for="line in items" :key="lineKey(line)">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span class="truncate mr-2">{{ line.name }} ×{{ line.qty }}</span>
                                <span class="shrink-0 tabular-nums">{{ line.is_bonus ? 'Gratis' : formatRupiah(line.qty * line.price) }}</span>
                            </div>
                            <div class="mt-1.5 pl-3">
                                <!-- Item bonus harganya 0 — diskon tidak relevan -->
                                <AppCurrencyInput v-if="!line.is_bonus" :model-value="line.discount" placeholder="Diskon item" @update:model-value="(v) => updateItem(line, { discount: v })" />
                                <p v-else class="text-xs text-green-600 dark:text-green-400">Gratis — tidak ada diskon.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div class="flex items-center justify-between gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Diskon Transaksi</span>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-800/60">
                                    <button
                                        v-for="opt in [{ key: 'nominal', label: 'Rp' }, { key: 'percent', label: '%' }]"
                                        :key="opt.key"
                                        type="button"
                                        @click="form.discount_type = opt.key"
                                        :class="[
                                            'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                            form.discount_type === opt.key
                                                ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                                : 'text-gray-500 dark:text-gray-400',
                                        ]"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <div class="w-36">
                                    <AppCurrencyInput v-if="form.discount_type === 'nominal'" v-model="form.discount_value" placeholder="0" />
                                    <AppNumberInput v-else v-model="form.discount_value" :min="0" :max="100" placeholder="0" />
                                </div>
                            </div>
                        </div>

                        <div v-if="lineDiscounts" class="flex justify-between text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <span>Diskon Item</span>
                            <span class="font-medium text-red-500">- {{ formatRupiah(lineDiscounts) }}</span>
                        </div>
                        <div v-if="headerDiscount" class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Diskon{{ form.discount_type === 'percent' ? ` (${form.discount_value}%)` : '' }}</span>
                            <span class="font-medium text-red-500">- {{ formatRupiah(headerDiscount) }}</span>
                        </div>

                        <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                            <span class="font-semibold text-gray-900 dark:text-white">Total</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">{{ formatRupiah(total) }}</span>
                        </div>
                    </div>
                </AppCard>

                <AppCard>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Catatan</label>
                    <textarea v-model="form.note" rows="2" placeholder="Opsional..." :class="inputClass(false)"></textarea>
                </AppCard>

                <div class="space-y-2">
                    <div v-for="held in heldCarts" :key="held.id"
                        class="flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ held.label || 'Tanpa label' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                {{ held.item_count }} item · {{ formatRupiah(held.total) }}<span v-if="held.customer_name"> · {{ held.customer_name }}</span>
                            </p>
                        </div>
                        <AppButton variant="outline" size="sm" :disabled="items.length > 0" @click="resumeCart(held)">Lanjutkan</AppButton>
                        <button type="button" @click="discardHeld(held)"
                            class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-400/10 transition-colors"
                            aria-label="Hapus transaksi tahan">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <div v-if="items.length" class="flex gap-2">
                        <input v-model="holdLabel" type="text" maxlength="100" placeholder="Label tahan (opsional)" :class="inputClass(false)" />
                        <AppButton variant="outline" size="md" @click="holdCart">Tahan</AppButton>
                    </div>
                </div>

                <p v-if="errors.items" class="text-xs text-red-500 text-center">{{ errors.items }}</p>

                <AppButton variant="primary" size="lg" class="w-full" :loading="saving" :disabled="!canSave" @click="handleSave">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan...' : 'Simpan Penjualan' }}
                </AppButton>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- DESKTOP: 2 kolom (keranjang kiri, checkout kanan)   -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="hidden lg:grid lg:grid-cols-3 lg:gap-5">
            <!-- ── Kiri: pencarian + keranjang ── -->
            <div class="lg:col-span-2 flex flex-col lg:h-[calc(100vh-220px)] lg:min-h-[520px]">
                <!-- Search (sticky di mobile sudah ditangani tab; di sini normal) -->
                <div class="mb-4 shrink-0 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Tambah Produk</label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="productSearch"
                            type="text"
                            placeholder="Cari produk untuk dijual, atau ketik SKU..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>

                    <!-- Hasil pencarian -->
                    <div v-if="productSearch.trim() && searchResults.length"
                        class="mt-2 max-h-56 overflow-y-auto scrollbar-styled divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-200 dark:border-gray-800">
                        <div v-for="product in searchResults" :key="product.id"
                            class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ product.label }}</p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[10px] font-mono text-gray-500 dark:text-gray-400">{{ product.sku }}</span>
                                    <span v-if="product.code" class="rounded-full bg-orange-100 dark:bg-orange-900/40 px-2 py-0.5 text-[10px] font-mono font-medium text-orange-700 dark:text-orange-300">{{ product.code }}</span>
                                    <span class="text-xs text-gray-400 tabular-nums">{{ formatRupiah(product.sell_price) }}</span>
                                </div>
                                <p v-if="product.spec" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ product.spec }}</p>
                            </div>
                            <AppBadge v-if="product.inCart" color="green" size="sm">Di keranjang</AppBadge>
                            <AppButton variant="outline" size="sm" @click="addToCart(product)">
                                <Plus class="h-4 w-4" />
                                Tambah
                            </AppButton>
                        </div>
                    </div>
                    <p v-else-if="productSearch.trim()" class="mt-2 text-xs text-gray-400">Tidak ada produk cocok.</p>
                </div>

                <!-- Keranjang desktop (scroll sendiri) -->
                <div class="lg:flex-1 lg:min-h-0 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                    <div v-if="items.length === 0" class="py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                        Keranjang kosong. Cari & tambahkan produk di atas.
                    </div>
                    <div v-else class="scrollbar-styled h-full overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="line in items" :key="lineKey(line)" class="p-4">
                            <div class="flex items-center gap-3">
                                <!-- Nama item = tombol expand (buka spek) -->
                                <button type="button" @click="toggleExpand(line)"
                                    class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ line.name }}</p>
                                            <AppBadge v-if="line.is_bonus" color="green" size="sm">FREE</AppBadge>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ line.code }}</p>
                                    </div>
                                    <ChevronDown class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                        :class="line.expanded ? 'rotate-180' : ''" />
                                </button>

                                <!-- Qty stepper -->
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" @click="stepQty(line, -1)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                        −
                                    </button>
                                    <span class="w-10 text-center text-sm font-semibold text-gray-900 dark:text-white tabular-nums">{{ line.qty }}</span>
                                    <button type="button" @click="stepQty(line, 1)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                        +
                                    </button>
                                </div>

                                <!-- Subtotal (harga tidak bisa diedit di sini — editnya di panel Rincian) -->
                                <div class="w-32 shrink-0 text-right text-sm font-semibold tabular-nums"
                                    :class="line.is_bonus ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white'">
                                    {{ line.is_bonus ? 'Gratis' : formatRupiah(line.qty * line.price) }}
                                </div>

                                <!-- Bonus: hilang setelah ditekan -->
                                <button v-if="!line.is_bonus" type="button" @click="markAsBonus(line)"
                                    title="Jadikan bonus"
                                    class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-100 dark:hover:bg-green-400/10 transition-colors">
                                    <Gift class="h-4 w-4" />
                                </button>

                                <button type="button" @click="removeItem(line)"
                                    class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- Spek (muncul saat di-expand) -->
                            <div v-show="line.expanded" class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3.5 py-2.5">
                                <span class="text-xs text-gray-400">Harga</span>
                                <span class="text-xs tabular-nums text-gray-600 dark:text-gray-300">{{ formatRupiah(line.price) }}</span>
                                <span class="text-xs text-gray-400">Spek</span>
                                <span class="text-right text-xs text-gray-600 dark:text-gray-300">{{ line.spec || '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Kanan: checkout (sticky; scroll hanya di dalam card) ── -->
            <div class="lg:sticky lg:top-0 lg:self-start lg:max-h-[calc(100vh-220px)] lg:overflow-hidden">
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-4 lg:max-h-[calc(100vh-220px)] lg:overflow-y-auto scrollbar-styled">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <Receipt class="h-4 w-4 text-gray-400" />
                        Checkout
                    </h2>

                    <!-- Tanggal -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Tanggal <span class="text-red-500">*</span></label>
                        <AppDatePicker v-model="form.date" :error="!!errors.date" />
                        <p v-if="errors.date" class="mt-1 text-xs text-red-500">{{ errors.date }}</p>
                    </div>

                    <!-- Kasir -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kasir <span class="text-red-500">*</span></label>
                        <AppSelect v-model="form.user_id" :options="cashierOptions" placeholder="Pilih kasir..." :error="!!errors.user_id" />
                        <p v-if="errors.user_id" class="mt-1 text-xs text-red-500">{{ errors.user_id }}</p>
                    </div>

                    <!-- Customer -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Customer <span class="text-red-500">*</span></label>

                        <!-- Terpilih: card kecil bisa di-x -->
                        <div v-if="selectedCustomer"
                            class="flex items-center gap-3 rounded-lg border border-green-500/50 dark:border-green-500/40 bg-gray-50 dark:bg-gray-800 px-3 py-2">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-sm font-semibold">
                                {{ selectedCustomer.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ selectedCustomer.name }}</p>
                                    <AppBadge v-if="selectedCustomer.is_member" color="yellow" size="sm">★ Member</AppBadge>
                                </div>
                                <p v-if="selectedCustomer.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCustomer.phone }}</p>
                            </div>
                            <button type="button" @click="clearCustomer"
                                class="shrink-0 rounded-lg p-1 text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                aria-label="Hapus customer">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Belum dipilih: search + tombol tambah baru -->
                        <div v-else class="relative">
                            <input
                                v-model="customerSearch"
                                type="text"
                                placeholder="Cari nama atau nomor telepon..."
                                @focus="customerOpen = true"
                                @blur="() => setTimeout(() => customerOpen = false, 150)"
                                :class="inputClass(!!errors.customer_id)"
                            />

                            <div v-if="customerOpen && customerSearch.trim()"
                                class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl max-h-64 overflow-y-auto">
                                <button v-for="c in customerResults" :key="c.id" type="button"
                                    @mousedown.prevent @click="selectCustomer(c)"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-sm font-semibold">
                                        {{ c.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ c.name }}</p>
                                            <AppBadge v-if="c.is_member" color="yellow" size="sm">★ Member</AppBadge>
                                        </div>
                                        <p v-if="c.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ c.phone }}</p>
                                    </div>
                                </button>

                                <!-- Tidak ada hasil → tombol tambah baru -->
                                <button v-if="hasNoCustomerMatch" type="button"
                                    @mousedown.prevent @click="openCustomerModal"
                                    class="flex w-full items-center gap-2 border-t border-gray-100 dark:border-gray-700 px-3 py-2.5 text-left text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <UserPlus class="h-3.5 w-3.5 shrink-0" />
                                    <span class="text-sm">Tambah customer baru: "<span class="font-medium">{{ customerSearch.trim() }}</span>"</span>
                                </button>
                            </div>
                        </div>

                        <p v-if="errors.customer_id" class="mt-1 text-xs text-red-500">{{ errors.customer_id }}</p>
                    </div>

                    <!-- Status — tab, bukan dropdown -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                        <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-800/60">
                                <button
                                    v-for="opt in STATUS_TABS"
                                    :key="opt.id"
                                    type="button"
                                    @click="form.status = opt.id"
                                    :class="[
                                        'inline-flex min-w-0 flex-1 items-center justify-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                                        form.status === opt.id
                                            ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                            : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    <component :is="opt.icon" class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">{{ opt.label }}</span>
                                </button>
                        </div>
                    </div>

                    <!-- Metode bayar — tab juga -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Metode Bayar</label>
                        <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-800/60">
                            <button
                                v-for="opt in PAYMENT_TABS"
                                :key="opt.id"
                                type="button"
                                @click="form.payment = opt.id"
                                :class="[
                                    'inline-flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                    form.payment === opt.id
                                        ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                        : 'text-gray-500 dark:text-gray-400',
                                ]"
                            >
                                <component :is="opt.icon" class="h-3.5 w-3.5" />
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Nominal bayar + jatuh tempo (khusus Hutang / Keep) -->
                    <template v-if="isPartial">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Jumlah Bayar <span class="font-normal text-gray-400">(opsional)</span>
                            </label>
                            <AppCurrencyInput v-model="form.paid_amount" placeholder="0" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                Jatuh Tempo <span class="font-normal text-gray-400">(opsional)</span>
                            </label>
                            <AppDatePicker v-model="form.due_date" placeholder="Pilih tanggal jatuh tempo..." />
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3 py-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Sisa Hutang</span>
                            <span class="text-sm font-semibold text-red-500 tabular-nums">{{ formatRupiah(remaining) }}</span>
                        </div>
                    </template>

                    <!-- Catatan -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan</label>
                        <textarea v-model="form.note" rows="2" placeholder="Opsional..." :class="inputClass(false)"></textarea>
                    </div>

                    <!-- Rincian + diskon item (bisa dilipat) -->
                    <div v-if="items.length" class="border-t border-gray-100 dark:border-gray-800 pt-3">
                        <button type="button" @click="detailsOpen = !detailsOpen"
                            class="flex w-full items-center justify-between gap-2 text-left">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Rincian</span>
                            <ChevronDown class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform"
                                :class="detailsOpen ? 'rotate-180' : ''" />
                        </button>

                        <div v-show="detailsOpen" class="mt-3 space-y-3">
                            <div v-for="line in items" :key="lineKey(line)">
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span class="truncate mr-2">{{ line.name }} ×{{ line.qty }}</span>
                                    <span class="shrink-0 tabular-nums">{{ line.is_bonus ? 'Gratis' : formatRupiah(line.qty * line.price) }}</span>
                                </div>
                                <div class="mt-1.5 pl-3">
                                    <!-- Item bonus harganya 0 — diskon tidak relevan -->
                                    <AppCurrencyInput v-if="!line.is_bonus" :model-value="line.discount" placeholder="Diskon item" @update:model-value="(v) => updateItem(line, { discount: v })" />
                                    <p v-else class="text-xs text-green-600 dark:text-green-400">Gratis — tidak ada diskon.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Diskon transaksi -->
                    <div class="border-t border-gray-100 dark:border-gray-800 pt-3 space-y-2">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Diskon Transaksi</label>
                        <div class="flex items-center gap-2">
                            <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-800/60">
                                <button
                                    v-for="opt in [{ key: 'nominal', label: 'Rp' }, { key: 'percent', label: '%' }]"
                                    :key="opt.key"
                                    type="button"
                                    @click="form.discount_type = opt.key"
                                    :class="[
                                        'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                        form.discount_type === opt.key
                                            ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                            : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                            <div class="flex-1">
                                <AppCurrencyInput v-if="form.discount_type === 'nominal'" v-model="form.discount_value" placeholder="0" />
                                <AppNumberInput v-else v-model="form.discount_value" :min="0" :max="100" placeholder="0" />
                            </div>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="space-y-1.5 border-t border-gray-100 dark:border-gray-800 pt-3">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span class="tabular-nums">{{ formatRupiah(subtotal) }}</span>
                        </div>
                        <div v-if="lineDiscounts" class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Diskon Item</span>
                            <span class="tabular-nums text-red-500">- {{ formatRupiah(lineDiscounts) }}</span>
                        </div>
                        <div v-if="headerDiscount" class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Diskon{{ form.discount_type === 'percent' ? ` (${form.discount_value}%)` : '' }}</span>
                            <span class="tabular-nums text-red-500">- {{ formatRupiah(headerDiscount) }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5">
                            <span class="font-semibold text-gray-900 dark:text-white">Total</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">{{ formatRupiah(total) }}</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-for="held in heldCarts" :key="held.id"
                            class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 px-2.5 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium text-gray-900 dark:text-white">{{ held.label || 'Tanpa label' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                    {{ held.item_count }} item · {{ formatRupiah(held.total) }}<span v-if="held.customer_name"> · {{ held.customer_name }}</span>
                                </p>
                            </div>
                            <AppButton variant="outline" size="sm" :disabled="items.length > 0" @click="resumeCart(held)">Lanjutkan</AppButton>
                            <button type="button" @click="discardHeld(held)"
                                class="shrink-0 rounded-lg p-1 text-gray-400 hover:text-red-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                aria-label="Hapus transaksi tahan">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div v-if="items.length" class="flex gap-2">
                            <input v-model="holdLabel" type="text" maxlength="100" placeholder="Label tahan (opsional)" :class="inputClass(false)" />
                            <AppButton variant="outline" size="sm" @click="holdCart">Tahan</AppButton>
                        </div>
                    </div>

                    <p v-if="errors.items" class="text-xs text-red-500">{{ errors.items }}</p>

                    <AppButton variant="primary" size="md" class="w-full" :loading="saving" :disabled="!canSave" @click="handleSave">
                        <Save class="h-4 w-4" />
                        {{ saving ? 'Menyimpan...' : 'Simpan Penjualan' }}
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- ── Modal tambah customer baru ── -->
        <CustomerFormModal
            ref="customerModalRef"
            :show="showCustomerModal"
            :item="null"
            @close="showCustomerModal = false"
            @submit="handleCustomerSubmit"
        />
        </div>
    </AppLayout>
</template>
