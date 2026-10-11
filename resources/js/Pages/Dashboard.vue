<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { UserCheck, ShoppingCart, Wrench, Tag, Send, PackageCheck, Boxes, TrendingUp, TrendingDown, Wallet, ChevronDown, ChevronUp } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppStatCard from '@/Components/UI/AppStatCard.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    stats:     { type: Object, required: true },
    needPrice: { type: Array, default: () => [] },
    needPost:  { type: Array, default: () => [] },
});

const toast = useToast();
const page = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const statCards = computed(() => [
    { label: 'Penjualan Hari Ini', value: props.stats.sales_count_today, icon: ShoppingCart, color: 'orange' },
    { label: 'Pembelian Hari Ini', value: props.stats.purchases_count_today, icon: PackageCheck, color: 'blue' },
    { label: 'Total Pemasukan Hari Ini', value: formatRupiah(props.stats.income_today), icon: TrendingUp, color: 'green' },
    { label: 'Total Pengeluaran Hari Ini', value: formatRupiah(props.stats.outcome_today), icon: TrendingDown, color: 'red' },
    { label: 'Total Saldo', value: formatRupiah(props.stats.balance), icon: Wallet, color: 'purple' },
    { label: 'Customer', value: props.stats.customers, icon: UserCheck, color: 'green' },
    { label: 'Servis Aktif', value: props.stats.servis, icon: Wrench, color: 'yellow' },
    { label: 'Total Item', value: props.stats.items, icon: Boxes, color: 'blue' },
]);

const COLLAPSED_STAT_COUNT = 2;
const statExpanded = ref(false);

const priceItem = ref(null);
const priceSaving = ref(false);
const priceForm = reactive({ sell_price: '' });
const priceError = ref('');

const openPrice = (item) => {
    priceItem.value = item;
    priceForm.sell_price = '';
    priceError.value = '';
};

const closePrice = () => {
    priceItem.value = null;
};

const submitPrice = () => {
    const value = Number(priceForm.sell_price);
    if (!value || value < 1) {
        priceError.value = 'Harga jual wajib diisi.';
        return;
    }
    priceSaving.value = true;
    router.patch(route('stock.laptop.price', priceItem.value.id), { sell_price: value }, {
        preserveScroll: true,
        onSuccess: () => closePrice(),
        onError: (errors) => { priceError.value = Object.values(errors)[0] ?? 'Harga tidak valid.'; },
        onFinish: () => { priceSaving.value = false; },
    });
};

const postItem = ref(null);
const postSaving = ref(false);

const openPost = (item) => {
    postItem.value = item;
};

const closePost = () => {
    postItem.value = null;
};

const submitPost = () => {
    postSaving.value = true;
    router.patch(route('stock.laptop.post', postItem.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => closePost(),
        onFinish: () => { postSaving.value = false; },
    });
};

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';

const specLabels = { cpu: 'CPU', gpu: 'GPU', memory: 'Memory', storage: 'Storage', display: 'Display' };

const specRows = (item) => Object.entries(specLabels).map(([key, label]) => ({
    label,
    value: item.specification?.[key] || '-',
}));

const detailRows = (item) => [
    { label: 'Nama', value: item.name },
    { label: 'SKU', value: item.sku, mono: true },
    ...(item.code ? [{ label: 'Kode', value: item.code, mono: true }] : []),
    ...(item.supplier?.name ? [{ label: 'Supplier', value: item.supplier.name }] : []),
    { label: 'Garansi', value: item.warranty },
    { label: 'Harga Beli', value: formatRupiah(item.buy_price) },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-3 sm:mb-6">
            <div
                v-for="(card, index) in statCards"
                :key="card.label"
                :class="{ 'hidden sm:block': index >= COLLAPSED_STAT_COUNT && !statExpanded }"
            >
                <AppStatCard
                    :label="card.label"
                    :value="card.value"
                    :icon="card.icon"
                    :icon-color="card.color"
                />
            </div>
        </div>

        <button
            type="button"
            class="mb-6 flex w-full items-center justify-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400 sm:hidden"
            @click="statExpanded = !statExpanded"
        >
            {{ statExpanded ? 'Sembunyikan' : 'Lihat semua' }}
            <ChevronUp v-if="statExpanded" class="h-4 w-4" />
            <ChevronDown v-else class="h-4 w-4" />
        </button>

        <div v-if="needPrice.length || needPost.length" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <AppCard v-if="needPrice.length" :padded="false">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <Tag class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Perlu Dikasih Harga</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Laptop siap jual tapi harga jual masih kosong</p>
                    </div>
                    <span class="ml-auto text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 tabular-nums">
                        {{ needPrice.length }}
                    </span>
                </div>
                <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="item in needPrice" :key="item.id" class="flex items-center gap-3 px-5 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.name }}</p>
                            <p class="text-xs text-gray-500 font-mono truncate">{{ item.sku }}<span v-if="item.code"> · {{ item.code }}</span></p>
                        </div>
                        <AppButton variant="outline" size="sm" @click="openPrice(item)">Kasih Harga</AppButton>
                    </div>
                </div>
            </AppCard>

            <AppCard v-if="needPost.length" :padded="false">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Send class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Perlu Di-post</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Harga sudah ada, tinggal di-post</p>
                    </div>
                    <span class="ml-auto text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 tabular-nums">
                        {{ needPost.length }}
                    </span>
                </div>
                <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="item in needPost" :key="item.id" class="flex items-center gap-3 px-5 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.name }}</p>
                            <p class="text-xs text-gray-500 font-mono truncate">{{ item.sku }}<span v-if="item.code"> · {{ item.code }}</span></p>
                        </div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ formatRupiah(item.sell_price) }}</span>
                        <AppButton variant="primary" size="sm" @click="openPost(item)">Post</AppButton>
                    </div>
                </div>
            </AppCard>
        </div>

        <AppModal :show="!!priceItem" title="Kasih Harga Jual" size="md" @close="closePrice">
            <div v-if="priceItem" class="space-y-4">
                <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="row in detailRows(priceItem)" :key="row.label" class="grid grid-cols-3 gap-3 py-2.5">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                        <dd :class="['col-span-2 text-sm text-gray-900 dark:text-white', row.mono ? 'font-mono' : 'font-medium']">{{ row.value || '-' }}</dd>
                    </div>
                </dl>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Spesifikasi</p>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div v-for="row in specRows(priceItem)" :key="row.label" class="flex gap-2">
                            <dt class="w-16 shrink-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-500 pt-0.5">{{ row.label }}</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Fitur</p>
                    <div v-if="priceItem.features?.length" class="flex flex-wrap gap-1.5">
                        <span v-for="(feature, index) in priceItem.features" :key="`${feature}-${index}`"
                            class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-xs text-indigo-600 dark:text-indigo-300">{{ feature }}</span>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kelengkapan</p>
                    <div v-if="priceItem.accessories?.length" class="flex flex-wrap gap-1.5">
                        <span v-for="(accessory, index) in priceItem.accessories" :key="`${accessory}-${index}`"
                            class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-600 dark:text-amber-300">{{ accessory }}</span>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
                </div>

                <div>
                    <label :class="labelClass">Harga Jual <span class="text-red-500">*</span></label>
                    <AppCurrencyInput v-model="priceForm.sell_price" :error="!!priceError" placeholder="0" />
                    <p v-if="priceError" class="mt-1.5 text-xs text-red-500">{{ priceError }}</p>
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" size="sm" @click="closePrice">Batal</AppButton>
                <AppButton variant="primary" size="sm" :loading="priceSaving" @click="submitPrice">Simpan Harga</AppButton>
            </template>
        </AppModal>

        <AppModal :show="!!postItem" title="Post Laptop" size="md" @close="closePost">
            <div v-if="postItem" class="space-y-4">
                <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="row in detailRows(postItem)" :key="row.label" class="grid grid-cols-3 gap-3 py-2.5">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ row.label }}</dt>
                        <dd :class="['col-span-2 text-sm text-gray-900 dark:text-white', row.mono ? 'font-mono' : 'font-medium']">{{ row.value || '-' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 py-2.5">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Harga Jual</dt>
                        <dd class="col-span-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ formatRupiah(postItem.sell_price) }}</dd>
                    </div>
                </dl>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Spesifikasi</p>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div v-for="row in specRows(postItem)" :key="row.label" class="flex gap-2">
                            <dt class="w-16 shrink-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-500 pt-0.5">{{ row.label }}</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ row.value }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Fitur</p>
                    <div v-if="postItem.features?.length" class="flex flex-wrap gap-1.5">
                        <span v-for="(feature, index) in postItem.features" :key="`${feature}-${index}`"
                            class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-xs text-indigo-600 dark:text-indigo-300">{{ feature }}</span>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kelengkapan</p>
                    <div v-if="postItem.accessories?.length" class="flex flex-wrap gap-1.5">
                        <span v-for="(accessory, index) in postItem.accessories" :key="`${accessory}-${index}`"
                            class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-600 dark:text-amber-300">{{ accessory }}</span>
                    </div>
                    <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" size="sm" @click="closePost">Batal</AppButton>
                <AppButton variant="primary" size="sm" :loading="postSaving" @click="submitPost">Post Sekarang</AppButton>
            </template>
        </AppModal>
    </AppLayout>
</template>
