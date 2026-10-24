<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Eye, EyeOff, X } from 'lucide-vue-next';
import ItemLogCard from '@/Components/ItemLog/ItemLogCard.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    item:        { type: Object, required: true },
    withHistory: { type: Boolean, default: false },
});

const { can } = usePermission();

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const formatDateTime = (value) => (value
    ? new Date(String(value).replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const issues = computed(() => props.item.issue_notes ?? []);
const issueToggling = ref(null);

// Ceklist/unceklist kendala — sama seperti di halaman detail, log aktivitas tercatat otomatis.
const toggleIssue = (issue) => {
    issueToggling.value = issue.id;
    router.patch(route('stock.laptop.issues.toggle', [props.item.id, issue.id]), {}, {
        preserveScroll: true,
        onFinish: () => {
            issueToggling.value = null;
        },
    });
};

const specLabels = { cpu: 'CPU', gpu: 'GPU', memory: 'Memory', storage: 'Storage', display: 'Display' };

const specRows = computed(() => Object.entries(specLabels).map(([key, label]) => ({
    label,
    value: props.item.specification?.[key] || '-',
})));

const hasSellPrice = computed(() => Number(props.item.sell_price) > 0);

const summaryCells = computed(() => [
    { label: 'Supplier', value: props.item.supplier?.name || '—' },
    { label: 'Garansi', value: props.item.warranty || '—' },
    { label: 'Harga Beli', value: formatRupiah(props.item.buy_price) },
    {
        label: 'Harga Jual',
        value: hasSellPrice.value ? formatRupiah(props.item.sell_price) : 'Belum diisi',
        cls: hasSellPrice.value
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-amber-600 dark:text-amber-400',
    },
]);

const logs = computed(() => props.item.logs ?? []);
const logPreviewCount = 3;
const showAllLogs = ref(false);
const showLogs = ref(false);
const visibleLogs = computed(() => (showAllLogs.value ? logs.value : logs.value.slice(0, logPreviewCount)));

const showImage = ref(false);
const showLightbox = ref(false);

// Esc menutup lightbox lebih dulu; capture di document supaya tidak ikut menutup modal di belakangnya.
const onLightboxKeydown = (event) => {
    if (event.key !== 'Escape') return;
    event.stopImmediatePropagation();
    showLightbox.value = false;
};

watch(showLightbox, (open) => {
    if (open) {
        document.addEventListener('keydown', onLightboxKeydown, true);
    } else {
        document.removeEventListener('keydown', onLightboxKeydown, true);
    }
});

onUnmounted(() => document.removeEventListener('keydown', onLightboxKeydown, true));

const sectionTitle = 'text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
const toggleClass = 'inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2 py-1 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800';
</script>

<template>
    <div class="space-y-4">
        <!-- Identitas -->
        <div>
            <p class="text-base font-semibold leading-snug text-gray-900 dark:text-white">{{ item.name }}</p>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ item.sku }}
                </span>
                <span v-if="item.code"
                    class="rounded-full bg-orange-100 px-2 py-0.5 font-mono text-[11px] font-medium text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">
                    {{ item.code }}
                </span>
            </div>
        </div>

        <!-- Foto produk (opsional, default tersembunyi) -->
        <div v-if="item.image">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p :class="sectionTitle">Foto Produk</p>
                <button type="button" :class="toggleClass" @click="showImage = !showImage">
                    <Eye v-if="!showImage" class="h-3.5 w-3.5" />
                    <EyeOff v-else class="h-3.5 w-3.5" />
                    {{ showImage ? 'Sembunyikan' : 'Tampilkan' }}
                </button>
            </div>
            <div v-show="showImage"
                class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40">
                <button type="button" class="group relative block w-full cursor-zoom-in" @click="showLightbox = true">
                    <img :src="item.image" :alt="item.name"
                        class="h-48 w-full object-contain transition-opacity group-hover:opacity-90" />
                    <span
                        class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition-all group-hover:bg-black/20 group-hover:opacity-100">
                        <Eye class="h-6 w-6 text-white drop-shadow" />
                    </span>
                </button>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 gap-2.5">
            <div v-for="cell in summaryCells" :key="cell.label"
                class="rounded-lg border border-gray-100 bg-gray-50/60 px-3 py-2 dark:border-gray-800 dark:bg-gray-800/30">
                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ cell.label }}</p>
                <p :class="['mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-white', cell.cls]">{{ cell.value }}</p>
            </div>
        </div>

        <!-- Slot aksi (mis. input harga jual) -->
        <slot name="after-summary" />

        <!-- Spesifikasi -->
        <div class="rounded-xl border border-gray-200 p-3.5 dark:border-gray-800">
            <p :class="sectionTitle" class="mb-2.5">Spesifikasi</p>
            <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                <div v-for="row in specRows" :key="row.label" class="flex gap-2 text-sm">
                    <span class="w-16 shrink-0 pt-0.5 text-xs font-medium uppercase text-gray-500 dark:text-gray-500">{{ row.label }}</span>
                    <span class="text-gray-800 dark:text-gray-200">{{ row.value }}</span>
                </div>
            </div>
        </div>

        <!-- Fitur & kelengkapan -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-3.5 dark:border-gray-800">
                <p :class="sectionTitle" class="mb-2">Fitur</p>
                <div v-if="(item.features ?? []).length" class="flex flex-wrap gap-1.5">
                    <span v-for="(feature, index) in item.features" :key="`${feature}-${index}`"
                        class="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-xs text-indigo-600 dark:text-indigo-300">
                        {{ feature }}
                    </span>
                </div>
                <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3.5 dark:border-gray-800">
                <p :class="sectionTitle" class="mb-2">Kelengkapan</p>
                <div v-if="(item.accessories ?? []).length" class="flex flex-wrap gap-1.5">
                    <span v-for="(accessory, index) in item.accessories" :key="`${accessory}-${index}`"
                        class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-600 dark:text-amber-300">
                        {{ accessory }}
                    </span>
                </div>
                <p v-else class="text-xs text-gray-400 dark:text-gray-600">-</p>
            </div>
        </div>

        <!-- Kendala — ceklist/unceklist, log aktivitas tercatat otomatis -->
        <div class="rounded-xl border border-red-100 p-3.5 dark:border-red-500/20">
            <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-wider text-red-500 dark:text-red-400">Kendala</p>
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

        <!-- Riwayat aktivitas (opsional, default tersembunyi) -->
        <div v-if="withHistory" class="border-t border-gray-100 pt-4 dark:border-gray-800">
            <div class="flex items-center justify-between gap-2">
                <p :class="sectionTitle">Riwayat Aktivitas</p>
                <button type="button" :class="toggleClass" @click="showLogs = !showLogs">
                    <Eye v-if="!showLogs" class="h-3.5 w-3.5" />
                    <EyeOff v-else class="h-3.5 w-3.5" />
                    {{ showLogs ? 'Sembunyikan' : `Tampilkan (${logs.length})` }}
                </button>
            </div>

            <div v-show="showLogs" class="mt-3">
                <div v-if="logs.length" class="ml-1.5 space-y-4 border-l-2 border-gray-200 pl-4 dark:border-gray-800">
                    <ItemLogCard v-for="log in visibleLogs" :key="log.id" :log="log" />
                </div>
                <p v-else class="text-sm text-gray-400 dark:text-gray-500">Belum ada log aktivitas.</p>

                <button v-if="logs.length > logPreviewCount" type="button"
                    class="mt-3 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                    @click="showAllLogs = !showAllLogs">
                    {{ showAllLogs ? 'Tampilkan lebih sedikit' : `Lihat semua (${logs.length})` }}
                </button>
            </div>
        </div>

        <!-- Lightbox foto -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <div v-if="showLightbox && item.image"
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
</template>
