<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, LogIn, LogOut, Layers, Plus, Printer, Pencil, Eye, EyeOff, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import LaptopFormModal from '@/Components/Laptops/LaptopFormModal.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppConfirmModal from '@/Components/UI/AppConfirmModal.vue';
import ItemLogCard from '@/Components/ItemLog/ItemLogCard.vue';
import ItemLogForm from '@/Components/ItemLog/ItemLogForm.vue';
import { useToast } from '@/composables/useToast';
import { usePermission } from '@/composables/usePermission';
import { useConfirm } from '@/composables/useConfirm';
import { useItemPriceTag } from '@/composables/useItemPriceTag';

const props = defineProps({
    module:    { type: String, required: true },
    item:      { type: Object, required: true },
    backRoute: { type: String, required: true },
    history:   { type: Array, default: () => [] },
    logs:      { type: Array, default: () => [] },
    users:     { type: Array, default: () => [] },
    brands:    { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
});

const showEditModal = ref(false);
const editModalRef = ref(null);

const handleEditSubmit = (payload) => {
    router.put(route('stock.laptop.update', props.item.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? 'Data tidak valid.'),
        onFinish: () => editModalRef.value?.stopLoading(),
    });
};

const page = usePage();
const toast = useToast();
const confirm = useConfirm();
const { addItemToPriceTag } = useItemPriceTag();

const printPriceTag = () => {
    addItemToPriceTag(props.item);
    toast.success(`${props.item.name} ditambahkan ke Price Tag.`);
};
const { can, hasRole } = usePermission();
const currentUserId = computed(() => page.props.auth?.user?.id);
const canDeleteLog = (log) => log.user_id === currentUserId.value || hasRole('OWNER') || hasRole('SYSADMIN');

const LAPTOP_STATUSES = ['ON PROSES', 'SIAP JUAL', 'PENDING', 'KENDALA', 'DISPLAY'];
const showLogModal = ref(false);
const logProcessing = ref(false);
const logPreviewCount = 3;
const showAllLogs = ref(false);
const visibleLogs = computed(() => (showAllLogs.value ? props.logs : props.logs.slice(0, logPreviewCount)));

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true },
);

const submitLog = (payload) => {
    logProcessing.value = true;
    router.post(route('stock.laptop.logs.store', props.item.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showLogModal.value = false;
        },
        onFinish: () => {
            logProcessing.value = false;
        },
    });
};

const deleteLog = async (log) => {
    const ok = await confirm.confirm({
        title: 'Hapus log aktivitas?',
        message: 'Log ini akan dihapus permanen.',
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('item-logs.destroy', log.id), { preserveScroll: true });
};

const moduleLabels = { laptop: 'Laptop', gadget: 'Gadget', aksesoris: 'Aksesoris', sparepart: 'Sparepart' };
const moduleLabel = computed(() => moduleLabels[props.module] ?? props.module);

const cardClass = 'rounded-xl p-4 border transition-colors bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800';
const sectionTitle = 'text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3';
const labelClass = 'text-xs text-gray-500 dark:text-gray-500 mb-1';

const STATUS_COLOR = {
    'SIAP JUAL':   'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
    'READY STOCK': 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
    'ON PROSES':   'text-amber-600 dark:text-amber-400 bg-amber-500/10 border-amber-500/20',
    'PENDING':     'text-amber-600 dark:text-amber-400 bg-amber-500/10 border-amber-500/20',
    'KENDALA':     'text-red-600 dark:text-red-400 bg-red-500/10 border-red-500/20',
    'DISPLAY':     'text-blue-600 dark:text-blue-400 bg-blue-500/10 border-blue-500/20',
    'HABIS':       'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
};

const MOVEMENT_LABEL = {
    in:         { text: 'Masuk',  icon: LogIn,  cls: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20' },
    out:        { text: 'Keluar', icon: LogOut, cls: 'text-red-600 dark:text-red-400 bg-red-500/10 border-red-500/20' },
    adjustment: { text: 'Adjust', icon: Layers, cls: 'text-amber-600 dark:text-amber-400 bg-amber-500/10 border-amber-500/20' },
};

// Status mutasi: pembelian/refund masuk, terjual/bonus/hutang keluar.
const MOVEMENT_STATUS = {
    PEMBELIAN:   { text: 'Pembelian',   cls: 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20' },
    REFUND:      { text: 'Refund',      cls: 'text-teal-700 dark:text-teal-400 bg-teal-500/10 border-teal-500/20' },
    TERJUAL:     { text: 'Terjual',     cls: 'text-indigo-700 dark:text-indigo-400 bg-indigo-500/10 border-indigo-500/20' },
    BONUS:       { text: 'Bonus',       cls: 'text-fuchsia-700 dark:text-fuchsia-400 bg-fuchsia-500/10 border-fuchsia-500/20' },
    HUTANG:      { text: 'Hutang',      cls: 'text-amber-700 dark:text-amber-400 bg-amber-500/10 border-amber-500/20' },
    PENYESUAIAN: { text: 'Penyesuaian', cls: 'text-yellow-700 dark:text-yellow-400 bg-yellow-500/10 border-yellow-500/20' },
};

const rupiah = (value) => (value === null || value === undefined || value === ''
    ? '—'
    : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value));

const formatDateTime = (value) => (value
    ? new Date(String(value).replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const formatDate = (value) => (value
    ? new Date(String(value).replace(' ', 'T')).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    : '—');

const stockHistory = computed(() =>
    [...props.history].sort((a, b) => (b.movement_date ?? '').localeCompare(a.movement_date ?? '') || (b.id - a.id)),
);

const isLaptop = computed(() => props.module === 'laptop');
const hasAccessories = computed(() => props.module === 'laptop' || props.module === 'gadget');
const hasSpecification = computed(() => ['laptop', 'gadget'].includes(props.module));
const hasImage = computed(() => hasSpecification.value && !!props.item.image);
const showImage = ref(false);
const showLightbox = ref(false);

// Esc menutup lightbox foto.
const onLightboxKeydown = (event) => {
    if (event.key === 'Escape') {
        showLightbox.value = false;
    }
};

watch(showLightbox, (open) => {
    if (open) {
        window.addEventListener('keydown', onLightboxKeydown);
    } else {
        window.removeEventListener('keydown', onLightboxKeydown);
    }
});

onUnmounted(() => window.removeEventListener('keydown', onLightboxKeydown));

const infoCells = computed(() => {
    const cells = [
        { label: 'Brand', value: props.item.brand_name },
        { label: 'Supplier', value: props.item.supplier_name },
        { label: 'Stok', value: props.item.stock, lowStock: Number(props.item.stock ?? 0) < 5 },
        { label: 'Status', status: props.item.status },
    ];
    if (isLaptop.value || props.module === 'gadget') {
        cells.push({ label: 'Serial Number', value: props.item.serialnumber, mono: true });
    }
    cells.push({ label: 'Garansi', value: props.item.warranty });
    if (props.module === 'sparepart') {
        cells.push({ label: 'Kategori', value: props.item.category_name });
    }
    cells.push({ label: 'Harga Beli', value: rupiah(props.item.buy_price), tone: 'bg-gray-50 dark:bg-gray-800/50' });
    cells.push({ label: 'Harga Jual', value: rupiah(props.item.sell_price), tone: 'bg-indigo-50 dark:bg-indigo-500/10' });
    return cells;
});

const specLabels = { cpu: 'CPU', gpu: 'GPU', memory: 'Memory', storage: 'Storage', display: 'Display' };

const specRows = computed(() => Object.entries(specLabels).map(([key, label]) => ({
    label,
    value: props.item.specification?.[key] || '-',
})));

const issues = computed(() => props.item.issue_notes ?? []);
const showIssueModal = ref(false);
const issueText = ref('');
const issueProcessing = ref(false);
const issueToggling = ref(null);

const submitIssue = () => {
    if (!issueText.value.trim()) return;
    issueProcessing.value = true;
    router.post(route('stock.laptop.issues.store', props.item.id), { text: issueText.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => {
            issueText.value = '';
            showIssueModal.value = false;
        },
        onFinish: () => {
            issueProcessing.value = false;
        },
    });
};

const toggleIssue = (issue) => {
    issueToggling.value = issue.id;
    router.patch(route('stock.laptop.issues.toggle', [props.item.id, issue.id]), {}, {
        preserveScroll: true,
        onFinish: () => {
            issueToggling.value = null;
        },
    });
};

const formatMovementQuantity = (movement) => {
    if (movement.type === 'in') return `+${movement.quantity}`;
    if (movement.type === 'out') return `−${movement.quantity}`;
    return `set ${movement.quantity}`;
};
</script>

<template>
    <Head :title="`${item.name} — Detail`" />

    <AppLayout :title="`${moduleLabel} — Detail`">
        <div class="flex-1 min-h-0 flex flex-col gap-4">

            <div class="shrink-0 flex flex-wrap items-center justify-between gap-3">
                <Link :href="route(backRoute)"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke daftar {{ moduleLabel.toLowerCase() }}
                </Link>
                <div v-if="hasSpecification" class="flex flex-wrap items-center gap-2">
                    <AppButton v-if="isLaptop && can('edit laptop')" variant="outline" size="sm" @click="showEditModal = true">
                        <Pencil class="h-3.5 w-3.5" />
                        Edit
                    </AppButton>
                    <AppButton variant="outline" size="sm" @click="printPriceTag">
                        <Printer class="h-3.5 w-3.5" />
                        Print Price Tag
                    </AppButton>
                </div>
            </div>

            <!--
                Urutan mobile: Informasi umum → Foto → Spesifikasi → Fitur → Aksesori → Kendala
                                → Riwayat Aktivitas → Riwayat Stok (mutasi).
                Desktop: dua kolom flex (kiri: info, log, mutasi; kanan: foto, spek, fitur, aksesori,
                kendala) supaya tinggi tiap card mengikuti isinya sendiri, tidak ikut meregang.
                Source order = urutan mobile.
            -->
            <div class="scrollbar-styled scrollbar-mobile-hidden flex-1 min-h-0 overflow-auto flex flex-col gap-4 lg:grid lg:grid-cols-3 lg:items-start lg:gap-4">

                <!-- Kolom kiri (di mobile dibuka via `contents` supaya urutan tetap) -->
                <div class="contents lg:col-span-2 lg:flex lg:flex-col lg:gap-4">

                <!-- 1. Informasi umum — kiri (col 1-2) -->
                <div :class="cardClass" class="order-1 lg:order-none">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 :class="sectionTitle" class="mb-0">Informasi Umum</h2>
                        <span v-if="item.code" class="rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 font-mono text-xs font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ item.code }}
                        </span>
                    </div>

                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ item.name }}</p>
                    <p class="mb-4 font-mono text-sm text-gray-500 dark:text-gray-400">{{ item.sku }}</p>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <div v-for="cell in infoCells" :key="cell.label" :class="cell.tone ? ['rounded-lg p-3', cell.tone] : ''">
                            <p :class="labelClass">{{ cell.label }}</p>
                            <span v-if="cell.status !== undefined"
                                :class="['inline-block rounded-full border px-2 py-0.5 text-xs font-medium', STATUS_COLOR[cell.status] ?? STATUS_COLOR['HABIS']]">
                                {{ cell.status || '-' }}
                            </span>
                            <p v-else
                                :class="['text-sm', cell.mono ? 'font-mono font-medium' : '',
                                    cell.lowStock ? 'font-semibold text-red-500' : 'text-gray-900 dark:text-white']">
                                {{ cell.value || '-' }}
                            </p>
                        </div>
                    </div>

                    <p v-if="item.notes" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-gray-800/50 dark:text-gray-300">
                        {{ item.notes }}
                    </p>
                </div>

                <!-- 6. Riwayat aktivitas — kiri -->
                <div v-if="isLaptop" :class="cardClass" class="order-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 :class="sectionTitle" class="mb-0">Riwayat Aktivitas</h2>
                        <AppButton v-if="can('edit laptop')" size="sm" @click="showLogModal = true">
                            <Plus class="h-3.5 w-3.5" />
                            Tambah Log
                        </AppButton>
                    </div>

                    <div v-if="logs.length" class="ml-1.5 space-y-4 border-l-2 border-gray-200 pl-4 dark:border-gray-800">
                        <ItemLogCard v-for="log in visibleLogs" :key="log.id" :log="log"
                            :can-delete="canDeleteLog(log)"
                            @delete="deleteLog" />
                    </div>
                    <p v-else class="text-sm text-gray-400 dark:text-gray-500">Belum ada log aktivitas.</p>

                    <button v-if="logs.length > logPreviewCount" type="button"
                        class="mt-4 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                        @click="showAllLogs = !showAllLogs">
                        {{ showAllLogs ? 'Tampilkan lebih sedikit' : `Lihat semua (${logs.length})` }}
                    </button>
                </div>

                <!-- 7. Riwayat stok (mutasi) — kiri -->
                <div :class="cardClass" class="order-7">
                    <h2 :class="sectionTitle">Riwayat Stok</h2>
                    <div v-if="stockHistory.length" class="space-y-2.5">
                        <div v-for="movement in stockHistory" :key="movement.id"
                            class="rounded-lg border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-gray-800/30">
                            <div class="mb-2 flex flex-wrap items-center gap-1.5">
                                <span :class="['rounded-full border px-2 py-0.5 text-[11px] font-semibold', (MOVEMENT_LABEL[movement.type] ?? MOVEMENT_LABEL.adjustment).cls]">
                                    {{ (MOVEMENT_LABEL[movement.type] ?? MOVEMENT_LABEL.adjustment).text }}
                                </span>
                                <span v-if="movement.status"
                                    :class="['rounded-full border px-2 py-0.5 text-[11px] font-semibold', (MOVEMENT_STATUS[movement.status] ?? MOVEMENT_STATUS.TERJUAL).cls]">
                                    {{ (MOVEMENT_STATUS[movement.status] ?? { text: movement.status }).text }}
                                </span>
                                <span class="ml-auto text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                                    {{ formatDate(movement.movement_date) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium tabular-nums text-gray-800 dark:text-gray-200">
                                    {{ formatMovementQuantity(movement) }}
                                </p>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                    <span>Stok: {{ movement.stock_before }} → <span class="font-medium text-gray-700 dark:text-gray-300">{{ movement.stock_after }}</span></span>
                                    <span v-if="movement.user_name">· {{ movement.user_name }}</span>
                                </div>
                                <p v-if="movement.reference || movement.notes" class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="movement.reference" class="font-mono">{{ movement.reference }}</span>
                                    <span v-if="movement.reference && movement.notes"> · </span>
                                    <span v-if="movement.notes">{{ movement.notes }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 dark:text-gray-500">Belum ada mutasi stok tercatat.</p>
                </div>
                </div>

                <!-- Kolom kanan -->
                <div class="contents lg:flex lg:flex-col lg:gap-4">

                <!-- 2a. Foto produk (opsional, default tersembunyi) — kanan -->
                <div v-if="hasImage" :class="cardClass" class="order-2 lg:order-none">
                    <div class="flex items-center justify-between gap-2" :class="showImage ? 'mb-3' : ''">
                        <h2 :class="sectionTitle" class="mb-0">Foto Produk</h2>
                        <button type="button" @click="showImage = !showImage"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2 py-1 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            <Eye v-if="!showImage" class="h-3.5 w-3.5" />
                            <EyeOff v-else class="h-3.5 w-3.5" />
                            {{ showImage ? 'Sembunyikan' : 'Tampilkan' }}
                        </button>
                    </div>
                    <div v-show="showImage" class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40">
                        <button type="button" class="group relative block w-full cursor-zoom-in" @click="showLightbox = true">
                            <img :src="item.image" :alt="item.name" class="h-48 w-full object-contain transition-opacity group-hover:opacity-90" />
                            <span class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition-all group-hover:bg-black/20 group-hover:opacity-100">
                                <Eye class="h-6 w-6 text-white drop-shadow" />
                            </span>
                        </button>
                    </div>
                </div>

                <!-- 2b. Spesifikasi — kanan -->
                <div v-if="hasSpecification" :class="cardClass" class="order-2 lg:order-none">
                    <h2 :class="sectionTitle">Spesifikasi</h2>
                    <div class="space-y-2">
                        <div v-for="row in specRows" :key="row.label" class="flex gap-3 text-sm">
                            <span class="w-20 shrink-0 pt-0.5 text-xs font-medium uppercase text-gray-500 dark:text-gray-500">{{ row.label }}</span>
                            <span class="text-gray-800 dark:text-gray-200">{{ row.value }}</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Fitur — kanan, laptop only -->
                <div v-if="isLaptop" :class="cardClass" class="order-3 lg:order-none">
                    <h2 :class="sectionTitle">Fitur</h2>
                    <div v-if="(item.features ?? []).length" class="flex flex-wrap gap-1.5">
                        <span v-for="(tag, index) in (item.features ?? [])" :key="`f-${index}`"
                            class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-xs text-indigo-600 dark:text-indigo-300">
                            {{ tag }}
                        </span>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
                </div>

                <!-- 4. Aksesori — kanan, laptop/gadget -->
                <div v-if="hasAccessories" :class="cardClass" class="order-4 lg:order-none">
                    <h2 :class="sectionTitle">Aksesori</h2>
                    <div v-if="(item.accessories ?? []).length" class="flex flex-wrap gap-1.5">
                        <span v-for="(tag, index) in (item.accessories ?? [])" :key="`a-${index}`"
                            class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-600 dark:text-amber-300">
                            {{ tag }}
                        </span>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
                </div>

                <!-- 5. Kendala — kanan -->
                <div v-if="hasSpecification" :class="cardClass" class="order-5 border-red-100 lg:order-none dark:border-red-500/20">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-red-500 dark:text-red-400">Kendala</h2>
                        <AppButton v-if="can('edit laptop')" size="sm" variant="outline" @click="showIssueModal = true">
                            <Plus class="h-3.5 w-3.5" />
                            Tambah
                        </AppButton>
                    </div>
                    <div v-if="issues.length" class="space-y-1.5">
                        <label v-for="issue in issues" :key="issue.id"
                            :class="['flex items-start gap-2.5 rounded-lg border p-2 text-xs transition-colors',
                                    issue.solved ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-red-500/20 bg-red-500/5']">
                            <input type="checkbox" :checked="issue.solved" :disabled="!can('edit laptop') || issueToggling === issue.id"
                                class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                @change="toggleIssue(issue)" />
                            <div class="min-w-0 flex-1">
                                <p :class="issue.solved ? 'text-gray-500 line-through dark:text-gray-500' : 'text-gray-800 dark:text-gray-200'">{{ issue.text }}</p>
                                <p v-if="issue.solved" class="mt-0.5 text-[10px] text-emerald-600 dark:text-emerald-400">
                                    ✓ Selesai {{ formatDateTime(issue.solved_at) }}
                                </p>
                            </div>
                        </label>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">Tidak ada kendala.</p>
                </div>

                </div>
            </div>

            <AppModal :show="showLogModal" title="Tambah Log Aktivitas" size="md" @close="showLogModal = false">
                <ItemLogForm
                    :specification="item.specification"
                    :users="users"
                    :statuses="LAPTOP_STATUSES"
                    :processing="logProcessing"
                    @submit="submitLog"
                    @cancel="showLogModal = false" />
            </AppModal>

            <LaptopFormModal
                v-if="isLaptop"
                ref="editModalRef"
                :show="showEditModal"
                :item="item"
                :brands="brands"
                :suppliers="suppliers"
                @close="showEditModal = false"
                @submit="handleEditSubmit"
            />

            <AppModal :show="showIssueModal" title="Tambah Kendala" size="sm" @close="showIssueModal = false">
                <form class="space-y-3" @submit.prevent="submitIssue">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nama kendala</label>
                        <input v-model="issueText" type="text" maxlength="255" autofocus
                            placeholder="Contoh: Keyboard rusak"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>
                    <div class="flex gap-3 pt-1">
                        <AppButton variant="secondary" class="flex-1" @click="showIssueModal = false">Batal</AppButton>
                        <AppButton type="submit" class="flex-1" :disabled="!issueText.trim()" :loading="issueProcessing">Simpan</AppButton>
                    </div>
                </form>
            </AppModal>

            <!-- Lightbox foto produk -->
            <Teleport to="body">
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-to-class="opacity-0"
                >
                    <div v-if="showLightbox && hasImage"
                        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm sm:p-8"
                        @click.self="showLightbox = false">
                        <button type="button" @click="showLightbox = false"
                            class="absolute right-4 top-4 rounded-lg p-2 text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                            aria-label="Tutup">
                            <X class="h-6 w-6" />
                        </button>
                        <img :src="item.image" :alt="item.name"
                            class="max-h-[88vh] max-w-full rounded-lg object-contain shadow-2xl" />
                    </div>
                </Transition>
            </Teleport>
        </div>
    </AppLayout>
</template>
