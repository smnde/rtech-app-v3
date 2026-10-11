<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, Trash2, Search, ShoppingBag,
    Receipt, Save, Truck, CreditCard, ChevronDown,
    Clock, CheckCircle2, Banknote,
    UserPlus, Users, Building2, X, Smartphone, Headphones, Laptop, Cpu, PackagePlus,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppNumberInput from '@/Components/UI/AppNumberInput.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';
import CustomerFormModal  from '@/Components/Customers/CustomerFormModal.vue';
import SupplierFormModal  from '@/Components/Suppliers/SupplierFormModal.vue';
import SparepartFormModal from '@/Components/Spareparts/SparepartFormModal.vue';
import AccessoryFormModal from '@/Components/Accessories/AccessoryFormModal.vue';
import LaptopFormModal    from '@/Components/Laptops/LaptopFormModal.vue';
import GadgetFormModal    from '@/Components/Gadgets/GadgetFormModal.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    cart: { type: Object, required: true },
    heldCarts: { type: Array, default: () => [] },
});

const page = usePage();

// ─── Header transaksi ─────────────────────────────────────────
// `buy_from_reference` = no. nota dari supplier (opsional, diinput manual).
// `invoice` TIDAK ada di form — dibuat otomatis oleh BE.
const form = ref({
    date: new Date().toISOString().slice(0, 10),
    buy_from: 'supplier',      // supplier | customer — sumber barang
    supplier_id: '',
    customer_id: '',
    jenis: 'non_po',           // non_po | po — hanya berarti kalau beli dari supplier
    buy_from_reference: '',
    status: 'lunas',           // lunas | hutang
    payment: 'cash',           // cash | tf
    note: '',
});

/** Beli dari supplier → PO/Non-PO relevan; beli dari customer → tidak. */
const buyFromSupplier = computed(() => form.value.buy_from === 'supplier');

// Sumber pembelian — tab di paling atas card checkout.
const SOURCE_TABS = [
    { id: 'supplier', label: 'Supplier', icon: Building2 },
    { id: 'customer', label: 'Customer', icon: Users },
];

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
        .filter((p) => p.label.toLowerCase().includes(q) || p.code.toLowerCase().includes(q))
        .map((p) => ({ ...p, inCart: inCart.has(lineKey(p)) }));
});

const addToCart = (product) => {
    router.post(route('transaksi.keranjang.item.store', 'purchase'), {
        item_type: product.item_type,
        item_id: product.id,
        price: product.buy_price,
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

const holdLabel = ref('');

const holdCart = () => {
    router.post(route('transaksi.keranjang.hold', 'purchase'), { label: holdLabel.value || null }, {
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

const total = computed(() => subtotal.value);

const totalQty = computed(() => items.value.reduce((s, l) => s + Number(l.qty || 0), 0));

const formatRupiah = (v) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;

// ─── Validasi & simpan ────────────────────────────────────────
const errors = ref({});
const saving = ref(false);

const validate = () => {
    const e = {};
    if (!form.value.date) e.date = 'Tanggal wajib diisi.';
    if (buyFromSupplier.value && !selectedSupplier.value) e.supplier_id = 'Supplier wajib dipilih.';
    if (!buyFromSupplier.value && !selectedCustomer.value) e.customer_id = 'Customer wajib dipilih.';
    if (items.value.length === 0) e.items = 'Keranjang masih kosong.';
    if (items.value.some((l) => Number(l.qty || 0) < 1)) e.items = 'Jumlah minimal 1.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

/**
 * Syarat transaksi boleh disimpan: sumbernya (supplier/customer) sudah dipilih,
 * keranjang tidak kosong, dan semua qty minimal 1.
 */
const canSave = computed(() =>
    items.value.length > 0
    && (buyFromSupplier.value ? !!selectedSupplier.value : !!selectedCustomer.value)
    && !items.value.some((l) => Number(l.qty || 0) < 1),
);

const handleSave = () => {
    if (!validate()) return;

    router.post(route('transaksi.pembelian.store'), {
        supplier_id: buyFromSupplier.value ? (selectedSupplier.value?.id ?? null) : null,
        customer_id: buyFromSupplier.value ? null : (props.cart.customer_id ?? null),
        buy_from_reference: form.value.buy_from_reference || null,
        payment_status: form.value.status,
        po_type: buyFromSupplier.value ? form.value.jenis : 'non_po',
        paid_at: null,
        notes: form.value.note || null,
        items: items.value.map((l) => ({
            item_type: l.item_type,
            item_id: l.item_id,
            qty: Number(l.qty),
            price: Number(l.price),
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

const STATUS_TABS = [
    { id: 'lunas',  label: 'Lunas',  icon: CheckCircle2 },
    { id: 'hutang', label: 'Hutang', icon: Clock },
];

// Metode pembayaran — juga tab, bukan dropdown.
const PAYMENT_TABS = [
    { id: 'cash', label: 'Cash', icon: Banknote },
    { id: 'tf',   label: 'Transfer', icon: CreditCard },
];

// Rincian item di panel checkout bisa dilipat supaya panel tidak memanjang.
const detailsOpen = ref(true);

// ─── Supplier dari DB + picker (pola sama dengan customer di Penjualan) ──
const suppliers = ref(props.suppliers.map((s) => ({ ...s })));

const selectedSupplier = ref(null);
const supplierSearch   = ref('');
const supplierOpen     = ref(false);

const supplierResults = computed(() => {
    const q = supplierSearch.value.trim().toLowerCase();
    if (!q) return suppliers.value;
    return suppliers.value.filter((s) =>
        s.name.toLowerCase().includes(q) || (s.phone ?? '').includes(q),
    );
});

const hasNoSupplierMatch = computed(() => {
    const q = supplierSearch.value.trim();
    return q.length > 0 && supplierResults.value.length === 0;
});

const selectSupplier = (supplier) => {
    selectedSupplier.value = supplier;
    form.value.supplier_id = supplier.id;
    supplierSearch.value = '';
    supplierOpen.value = false;
    errors.value.supplier_id = '';
};

const clearSupplier = () => {
    selectedSupplier.value = null;
    form.value.supplier_id = '';
};

// ─── Customer dari DB + picker (dipakai saat beli dari customer) ──
const customers = ref(props.customers.map((c) => ({ ...c })));

const selectedCustomer = computed(() =>
    customers.value.find((c) => c.id === props.cart.customer_id) ?? null,
);
const customerSearch   = ref('');
const customerOpen     = ref(false);

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
    selectedCustomer.value = customer;
    form.value.customer_id = customer.id;
    customerSearch.value = '';
    customerOpen.value = false;
    errors.value.customer_id = '';
    router.put(route('transaksi.keranjang.customer', 'purchase'), { customer_id: customer.id }, { preserveScroll: true });
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.value.customer_id = '';
    router.put(route('transaksi.keranjang.customer', 'purchase'), { customer_id: null }, { preserveScroll: true });
};

/** Ganti sumber pembelian → bersihkan pilihan lawan supaya tidak ada sisa. */
const setBuyFrom = (source) => {
    form.value.buy_from = source;
    if (source === 'supplier') {
        clearCustomer();
    } else {
        clearSupplier();
    }
};

// ─── Modal tambah customer / supplier baru ────────────────────
const showCustomerModal = ref(false);
const showSupplierModal = ref(false);
const customerModalRef  = ref(null);
const supplierModalRef  = ref(null);

const openCustomerModal = () => { customerOpen.value = false; showCustomerModal.value = true; };
const openSupplierModal = () => { supplierOpen.value = false; showSupplierModal.value = true; };

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

const handleSupplierSubmit = (payload) => {
    router.post(route('supplier.store'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            const created = page.props.flash?.supplier;
            if (created) {
                suppliers.value.push(created);
                selectSupplier(created);
            }
            showSupplierModal.value = false;
        },
        onFinish: () => supplierModalRef.value?.stopLoading(),
    });
};

// ─── Tambah barang baru (kalau belum ada di database) ─────────
// User pilih jenis dulu, baru modal form yang sesuai dibuka.
const ITEM_TYPES = [
    { id: 'sparepart', label: 'Sparepart', icon: Cpu },
    { id: 'accessory', label: 'Aksesoris', icon: Headphones },
    { id: 'laptop',    label: 'Laptop',    icon: Laptop },
    { id: 'gadget',    label: 'Gadget',    icon: Smartphone },
];

const showItemTypeMenu = ref(false);

const showItemModal = ref({ sparepart: false, accessory: false, laptop: false, gadget: false });
// Ref top-level per modal — `ref="..."` di template harus berupa binding, bukan jalur bertitik.
const sparepartModalRef = ref(null);
const accessoryModalRef = ref(null);
const laptopModalRef    = ref(null);
const gadgetModalRef    = ref(null);

const itemModalRef = (type) => ({
    sparepart: sparepartModalRef,
    accessory: accessoryModalRef,
    laptop:    laptopModalRef,
    gadget:    gadgetModalRef,
}[type]);

const chooseItemType = (type) => {
    showItemTypeMenu.value = false;
    showItemModal.value[type] = true;
};

const closeItemModal = (type) => { showItemModal.value[type] = false; };

/**
 * Barang baru dari modal langsung masuk keranjang dan langsung terpilih,
 * supaya kasir tidak perlu mencari ulang barang yang baru dibuat.
 */
const STOCK_STORE_ROUTES = {
    sparepart: 'stock.sparepart.store',
    accessory: 'stock.aksesoris.store',
    laptop:    'stock.laptop.store',
    gadget:    'stock.gadget.store',
};

/**
 * Barang baru disimpan ke DB lalu masuk keranjang lewat flash `created_item`,
 * supaya id yang dipakai keranjang adalah id asli.
 */
const handleItemSubmit = (type, payload) => {
    router.post(route(STOCK_STORE_ROUTES[type]), payload, {
        preserveScroll: true,
        onSuccess: () => { showItemModal.value[type] = false; },
        onFinish: () => itemModalRef(type)?.value?.stopLoading(),
    });
};

watch(() => page.props.flash?.created_item, (created) => {
    if (!created) return;
    router.post(route('transaksi.keranjang.item.store', 'purchase'), {
        item_type: created.item_type,
        item_id: created.id,
        price: created.buy_price,
    }, { preserveScroll: true });
});
</script>

<template>
    <Head title="Tambah Pembelian" />

    <AppLayout title="Tambah Pembelian">
        <!-- Scroll hanya di dalam card, bukan sehalaman — pola sama dengan halaman list. -->
        <div class="flex h-full flex-col">
        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TOOLBAR: kembali + judul                            -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="mb-4 flex shrink-0 items-center gap-3">
            <Link :href="route('transaksi.pembelian')"
                class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                aria-label="Kembali">
                <ArrowLeft class="h-5 w-5" />
            </Link>
            <div class="min-w-0">
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Pembelian Baru</h1>
                <p class="hidden sm:block text-sm text-gray-500 dark:text-gray-400">
                    Restock produk dari supplier, atur jumlah & diskon, lalu simpan.
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
                    <!-- Tambah barang baru — pilih jenis dulu, lalu modal form yang sesuai -->
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Tambah Produk</span>
                        <div class="relative">
                            <AppButton variant="outline" size="sm" @click="showItemTypeMenu = !showItemTypeMenu">
                                <PackagePlus class="h-3.5 w-3.5" />
                                Barang Baru
                            </AppButton>

                            <div v-if="showItemTypeMenu"
                                class="absolute right-0 z-50 mt-1 w-44 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                                <button v-for="t in ITEM_TYPES" :key="t.id" type="button"
                                    @click="chooseItemType(t.id)"
                                    class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <component :is="t.icon" class="h-4 w-4 text-gray-400" />
                                    {{ t.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="productSearch"
                            type="text"
                            placeholder="Cari produk untuk restock..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                </AppCard>

                <div class="space-y-2">
                    <div
                        v-for="product in searchResults"
                        :key="lineKey(product)"
                        class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ product.label }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ product.code }} · {{ formatRupiah(product.buy_price) }}</p>
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
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ line.name }}</p>
                                    <p class="text-xs text-green-600 dark:text-green-400 font-semibold tabular-nums">
                                        {{ formatRupiah(line.qty * line.price) }}
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

                        <!-- Sumber pembelian — dari supplier atau dari customer -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Sumber Pembelian</label>
                            <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800/60">
                                <button
                                    v-for="opt in SOURCE_TABS"
                                    :key="opt.id"
                                    type="button"
                                    @click="setBuyFrom(opt.id)"
                                    :class="[
                                        'inline-flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                        form.buy_from === opt.id
                                            ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                            : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    <component :is="opt.icon" class="h-4 w-4" />
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Jenis: PO / Non-PO — hanya kalau beli dari supplier -->
                        <div v-if="buyFromSupplier">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Jenis Pembelian</label>
                            <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-1 bg-gray-50 dark:bg-gray-800/60">
                                <button
                                    v-for="opt in [
                                        { key: 'non_po', label: 'Non-PO', icon: ShoppingBag },
                                        { key: 'po',     label: 'PO',     icon: Truck },
                                    ]"
                                    :key="opt.key"
                                    type="button"
                                    @click="form.jenis = opt.key"
                                    :class="[
                                        'inline-flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                        form.jenis === opt.key
                                            ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                            : 'text-gray-500 dark:text-gray-400',
                                    ]"
                                >
                                    <component :is="opt.icon" class="h-4 w-4" />
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>

                        <!-- No. Nota supplier (opsional) — hanya kalau beli dari supplier -->
                        <div v-if="buyFromSupplier">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                No. Nota Supplier <span class="text-xs font-normal text-gray-400">(opsional)</span>
                            </label>
                            <input v-model="form.buy_from_reference" type="text"
                                placeholder="Nomor nota dari supplier"
                                :class="inputClass(false)" />
                        </div>

                        <!-- Supplier (picker) — hanya kalau beli dari supplier -->
                        <div v-if="buyFromSupplier">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Supplier <span class="text-red-500">*</span>
                            </label>

                            <div v-if="selectedSupplier"
                                class="flex items-center gap-3 rounded-lg border border-green-500/50 dark:border-green-500/40 bg-gray-50 dark:bg-gray-800 px-3 py-2.5">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold">
                                    {{ selectedSupplier.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ selectedSupplier.name }}</p>
                                    <p v-if="selectedSupplier.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedSupplier.phone }}</p>
                                </div>
                                <button type="button" @click="clearSupplier"
                                    class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                    aria-label="Hapus supplier">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <div v-else class="relative">
                                <input
                                    v-model="supplierSearch"
                                    type="text"
                                    placeholder="Cari nama atau telepon supplier..."
                                    @focus="supplierOpen = true"
                                    @blur="() => setTimeout(() => supplierOpen = false, 150)"
                                    :class="inputClass(!!errors.supplier_id)"
                                />

                                <div v-if="supplierOpen && supplierSearch.trim()"
                                    class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl max-h-64 overflow-y-auto">
                                    <button v-for="s in supplierResults" :key="s.id" type="button"
                                        @mousedown.prevent @click="selectSupplier(s)"
                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-sm font-semibold">
                                            {{ s.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ s.name }}</p>
                                            <p v-if="s.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ s.phone }}</p>
                                        </div>
                                    </button>

                                    <button v-if="hasNoSupplierMatch" type="button"
                                        @mousedown.prevent @click="openSupplierModal"
                                        class="flex w-full items-center gap-2 border-t border-gray-100 dark:border-gray-700 px-3 py-2.5 text-left text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                        <UserPlus class="h-4 w-4 shrink-0" />
                                        <span class="text-sm">Tambah supplier baru: "<span class="font-medium">{{ supplierSearch.trim() }}</span>"</span>
                                    </button>
                                </div>
                            </div>

                            <p v-if="errors.supplier_id" class="mt-1.5 text-xs text-red-500">{{ errors.supplier_id }}</p>
                        </div>

                        <!-- Customer (picker) — hanya kalau beli dari customer -->
                        <div v-else>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Customer <span class="text-red-500">*</span>
                            </label>

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
                                        'inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-2 text-xs font-medium transition-colors',
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
                    </div>
                </AppCard>
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
                                <span class="shrink-0 tabular-nums">{{ formatRupiah(line.qty * line.price) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-3">
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
                            <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ held.item_count }} item · {{ formatRupiah(held.total) }}</p>
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

                <AppButton variant="primary" size="lg" class="w-full" :loading="saving" @click="handleSave">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Menyimpan...' : 'Simpan Pembelian' }}
                </AppButton>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- DESKTOP: 2 kolom (keranjang kiri, checkout kanan)   -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="hidden lg:grid lg:grid-cols-3 lg:gap-5">
            <!-- ── Kiri: pencarian + keranjang ── -->
            <div class="lg:col-span-2 flex flex-col lg:h-[calc(100vh-220px)] lg:min-h-[520px]">
                <div class="mb-4 shrink-0 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Tambah Produk</label>

                        <!-- Tambah barang baru — pilih jenis dulu, lalu modal form yang sesuai -->
                        <div class="relative">
                            <AppButton variant="outline" size="sm" @click="showItemTypeMenu = !showItemTypeMenu">
                                <PackagePlus class="h-3.5 w-3.5" />
                                Barang Baru
                            </AppButton>

                            <div v-if="showItemTypeMenu"
                                class="absolute right-0 z-50 mt-1 w-44 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                                <button v-for="t in ITEM_TYPES" :key="t.id" type="button"
                                    @click="chooseItemType(t.id)"
                                    class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <component :is="t.icon" class="h-4 w-4 text-gray-400" />
                                    {{ t.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            v-model="productSearch"
                            type="text"
                            placeholder="Cari produk untuk restock, atau ketik SKU..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-9 pr-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>

                    <div v-if="productSearch.trim() && searchResults.length"
                        class="mt-2 max-h-56 overflow-y-auto scrollbar-styled divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-200 dark:border-gray-800">
                        <div v-for="product in searchResults" :key="lineKey(product)"
                            class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ product.label }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ product.code }} · {{ formatRupiah(product.buy_price) }}</p>
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
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ line.name }}</p>
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
                                <div class="w-32 shrink-0 text-right text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                                    {{ formatRupiah(line.qty * line.price) }}
                                </div>

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

                    <!-- Tanggal — paling atas card checkout -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Tanggal <span class="text-red-500">*</span></label>
                        <AppDatePicker v-model="form.date" :error="!!errors.date" />
                        <p v-if="errors.date" class="mt-1 text-xs text-red-500">{{ errors.date }}</p>
                    </div>

                    <!-- Sumber pembelian — dari supplier atau dari customer -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sumber Pembelian</label>
                        <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-800/60">
                            <button
                                v-for="opt in SOURCE_TABS"
                                :key="opt.id"
                                type="button"
                                @click="setBuyFrom(opt.id)"
                                :class="[
                                    'inline-flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                    form.buy_from === opt.id
                                        ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                        : 'text-gray-500 dark:text-gray-400',
                                ]"
                            >
                                <component :is="opt.icon" class="h-3.5 w-3.5" />
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Jenis (PO/Non-PO) hanya relevan kalau beli dari supplier -->
                    <div v-if="buyFromSupplier">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Jenis Pembelian</label>
                        <div class="inline-flex w-full rounded-lg border border-gray-300 dark:border-gray-700 p-0.5 bg-gray-50 dark:bg-gray-800/60">
                            <button
                                v-for="opt in [
                                    { key: 'non_po', label: 'Non-PO', icon: ShoppingBag },
                                    { key: 'po',     label: 'PO',     icon: Truck },
                                ]"
                                :key="opt.key"
                                type="button"
                                @click="form.jenis = opt.key"
                                :class="[
                                    'inline-flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                    form.jenis === opt.key
                                        ? 'bg-white dark:bg-gray-900 text-primary-700 dark:text-primary-400 shadow-sm'
                                        : 'text-gray-500 dark:text-gray-400',
                                ]"
                            >
                                <component :is="opt.icon" class="h-3.5 w-3.5" />
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- No. Nota supplier (dari supplier nota; opsional) — hanya kalau beli dari supplier -->
                    <div v-if="buyFromSupplier">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                            No. Nota Supplier <span class="font-normal text-gray-400">(opsional)</span>
                        </label>
                        <input v-model="form.buy_from_reference" type="text"
                            placeholder="Nomor nota dari supplier"
                            :class="inputClass(false)" />
                    </div>

                    <!-- Supplier (picker) — hanya kalau beli dari supplier -->
                    <div v-if="buyFromSupplier">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Supplier <span class="text-red-500">*</span></label>

                        <div v-if="selectedSupplier"
                            class="flex items-center gap-3 rounded-lg border border-green-500/50 dark:border-green-500/40 bg-gray-50 dark:bg-gray-800 px-3 py-2">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-sm font-semibold">
                                {{ selectedSupplier.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ selectedSupplier.name }}</p>
                                <p v-if="selectedSupplier.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedSupplier.phone }}</p>
                            </div>
                            <button type="button" @click="clearSupplier"
                                class="shrink-0 rounded-lg p-1 text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                aria-label="Hapus supplier">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <div v-else class="relative">
                            <input
                                v-model="supplierSearch"
                                type="text"
                                placeholder="Cari nama atau telepon supplier..."
                                @focus="supplierOpen = true"
                                @blur="() => setTimeout(() => supplierOpen = false, 150)"
                                :class="inputClass(!!errors.supplier_id)"
                            />

                            <div v-if="supplierOpen && supplierSearch.trim()"
                                class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl max-h-64 overflow-y-auto">
                                <button v-for="s in supplierResults" :key="s.id" type="button"
                                    @mousedown.prevent @click="selectSupplier(s)"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-sm font-semibold">
                                        {{ s.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ s.name }}</p>
                                        <p v-if="s.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ s.phone }}</p>
                                    </div>
                                </button>

                                <button v-if="hasNoSupplierMatch" type="button"
                                    @mousedown.prevent @click="openSupplierModal"
                                    class="flex w-full items-center gap-2 border-t border-gray-100 dark:border-gray-700 px-3 py-2.5 text-left text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                                    <UserPlus class="h-3.5 w-3.5 shrink-0" />
                                    <span class="text-sm">Tambah supplier baru: "<span class="font-medium">{{ supplierSearch.trim() }}</span>"</span>
                                </button>
                            </div>
                        </div>

                        <p v-if="errors.supplier_id" class="mt-1 text-xs text-red-500">{{ errors.supplier_id }}</p>
                    </div>

                    <!-- Customer (picker) — hanya kalau beli dari customer -->
                    <div v-else>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Customer <span class="text-red-500">*</span></label>

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
                                    'inline-flex min-w-0 flex-1 items-center justify-center gap-1 rounded-md px-1.5 py-1.5 text-[11px] font-medium transition-colors',
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
                                    <span class="shrink-0 tabular-nums">{{ formatRupiah(line.qty * line.price) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="space-y-1.5 border-t border-gray-100 dark:border-gray-800 pt-3">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span class="tabular-nums">{{ formatRupiah(subtotal) }}</span>
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
                                <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ held.item_count }} item · {{ formatRupiah(held.total) }}</p>
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
                        {{ saving ? 'Menyimpan...' : 'Simpan Pembelian' }}
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- ── Modal tambah customer / supplier baru ── -->
        <CustomerFormModal
            ref="customerModalRef"
            :show="showCustomerModal"
            :item="null"
            @close="showCustomerModal = false"
            @submit="handleCustomerSubmit"
        />
        <SupplierFormModal
            ref="supplierModalRef"
            :show="showSupplierModal"
            :item="null"
            @close="showSupplierModal = false"
            @submit="handleSupplierSubmit"
        />

        <!-- ── Modal tambah barang baru (sesuai jenis yang dipilih) ── -->
        <SparepartFormModal
            ref="sparepartModalRef"
            :show="showItemModal.sparepart"
            :item="null"
            :categories="categories"
            :brands="brands"
            :suppliers="suppliers"
            @close="closeItemModal('sparepart')"
            @submit="handleItemSubmit('sparepart', $event)"
        />
        <AccessoryFormModal
            ref="accessoryModalRef"
            :show="showItemModal.accessory"
            :item="null"
            :brands="brands"
            :suppliers="suppliers"
            @close="closeItemModal('accessory')"
            @submit="handleItemSubmit('accessory', $event)"
        />
        <LaptopFormModal
            ref="laptopModalRef"
            :show="showItemModal.laptop"
            :item="null"
            :brands="brands"
            :suppliers="suppliers"
            @close="closeItemModal('laptop')"
            @submit="handleItemSubmit('laptop', $event)"
        />
        <GadgetFormModal
            ref="gadgetModalRef"
            :show="showItemModal.gadget"
            :item="null"
            :brands="brands"
            :suppliers="suppliers"
            @close="closeItemModal('gadget')"
            @submit="handleItemSubmit('gadget', $event)"
        />
        </div>
    </AppLayout>
</template>
