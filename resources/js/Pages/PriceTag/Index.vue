<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Printer, Search, ShoppingBasket, Plus, Trash2, Minus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppModal from '@/Components/UI/AppModal.vue';
import { usePriceTagBasket } from '@/composables/usePriceTagBasket';

const props = defineProps({ items: { type: Array, default: () => [] } });

const { basket, pages, totalLabels, add, remove, clear } = usePriceTagBasket('mt_price_tag_basket', 9);

const search = ref('');
const showBasket = ref(false);
const showManual = ref(false);
const manualForm = ref({ name: '', price: '', description: '' });

const searchResults = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return [];
    return props.items
        .filter((item) => item.name.toLowerCase().includes(keyword) || (item.code || '').toLowerCase().includes(keyword))
        .slice(0, 10);
});

function formatRupiah(value) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value || 0));
}

function pickItem(item) {
    add({
        id: item.id,
        code: item.code,
        name: item.name,
        price: item.sell_price || 0,
        description: item.specification || '-',
        warranty: item.warranty || '',
    });
    search.value = '';
}

function saveManual() {
    if (!manualForm.value.name.trim() || !manualForm.value.price) return;
    add({
        id: `manual-${Date.now()}`,
        code: `MAN-${String(Date.now()).slice(-6)}`,
        name: manualForm.value.name.trim(),
        price: Number(manualForm.value.price),
        description: manualForm.value.description || '-',
    });
    manualForm.value = { name: '', price: '', description: '' };
    showManual.value = false;
}

function alignSpec(text) {
    const lines = String(text || '-').toUpperCase().split('\n');
    const parsed = lines.map(ln => {
        const colonIdx = ln.indexOf(':');
        if (colonIdx === -1) return { key: ln, val: '' };
        return { key: ln.slice(0, colonIdx).trimEnd(), val: ln.slice(colonIdx + 1).trim() };
    });
    const maxLen = Math.max(...parsed.map(p => p.key.length));
    return parsed.map(({ key, val }) =>
        `<div>${key.padEnd(maxLen, '\u00a0')} : ${val}</div>`
    ).join('');
}

function handlePrint() {
    const w = window.open('', '_blank');
    if (!w) return;
    const style = `
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { font-family: 'Fira Code', monospace; margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page { width: 100%; box-sizing: border-box; page-break-after: always; display: grid; grid-template-columns: repeat(3, 8.7cm); grid-template-rows: repeat(3, 5.7cm); gap: 0.5cm; align-items: start; justify-content: center; }
            .page:last-child { page-break-after: avoid; }
            .label { width: 8.9cm; height: 5.7cm; border: 1px solid #000; border-radius: 4px; box-sizing: border-box; padding: 0.4cm; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
            .product-name { font-size: 14pt; background: #059669; color: #fff; text-align: center; margin: -0.4cm -0.4cm 0.2cm; padding: 0.2cm 0; line-height: 1.3; }
            .spec-box { padding: 0.2cm; font-size: 10pt; line-height: 1.35; color: #333; flex-grow: 1; overflow: hidden; }
            .warranty { font-size: 8pt; color: #555; font-style: italic; text-align: center; padding: 0.1cm; }
            .price { font-size: 13pt; background: #059669; color: #fff; text-align: center; margin: 0.2cm -0.4cm -0.4cm; padding: 0.2cm 0; }
        }`;
    const html = pages.value.map((pg) =>
        `<div class="page">${pg.map((l) =>
            `<div class="label">
                <div class="product-name">${String(l.name || '').toUpperCase()}</div>
                <div class="spec-box">${alignSpec(l.description)}</div>
                ${l.warranty ? `<div class="warranty">Garansi ${l.warranty}</div>` : ''}
                <div class="price">RP ${new Intl.NumberFormat('id-ID').format(Number(l.price || 0))},-</div>
            </div>`
        ).join('')}</div>`
    ).join('');
    w.document.write(`<html><head><title>Price Tag</title><style>${style}</style></head><body>${html}</body></html>`);
    w.document.close();
    w.focus();
    w.print();
}
</script>

<template>
    <AppLayout>
        <Head title="Price Tag" />

        <AppPageHeader class="shrink-0" title="Price Tag" :description="`${totalLabels} label · ${pages.length} halaman`">
            <template #actions>
                <AppButton variant="outline" @click="showManual = true">
                    <Plus class="h-4 w-4" />
                    Manual
                </AppButton>
                <AppButton variant="outline" @click="showBasket = true">
                    <ShoppingBasket class="h-4 w-4" />
                    <span>Basket</span>
                    <span
                        v-if="basket.length"
                        class="flex h-5 min-w-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[10px] font-bold leading-none text-white"
                    >
                        {{ basket.length }}
                    </span>
                </AppButton>
                <AppButton :disabled="!totalLabels" @click="handlePrint">
                    <Printer class="h-4 w-4" />
                    Print
                </AppButton>
            </template>
        </AppPageHeader>

        <div class="relative mb-6 shrink-0">
            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
                v-model="search"
                type="text"
                placeholder="Cari item (nama / SKU)..."
                class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
            />

            <div
                v-if="searchResults.length"
                class="absolute inset-x-0 top-full z-20 mt-1.5 max-h-64 overflow-auto rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
            >
                <button
                    v-for="item in searchResults"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-2.5 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                    @click="pickItem(item)"
                >
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ item.code }}</p>
                    </div>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ formatRupiah(item.sell_price) }}</span>
                </button>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Preview: 3×3 per halaman (A4 Landscape)
                </p>
                <p v-if="totalLabels" class="text-xs text-gray-400 dark:text-gray-500">
                    {{ pages.length }} halaman · {{ totalLabels }} label
                </p>
            </div>

            <div v-if="!totalLabels" class="flex flex-col items-center justify-center py-16 text-center">
                <p class="text-sm text-gray-400 dark:text-gray-500">Belum ada item</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-600">Cari item di atas atau tambah manual</p>
            </div>

            <div v-else class="scrollbar-styled min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                <div v-for="(pageLabels, pageIndex) in pages" :key="pageIndex">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        Halaman {{ pageIndex + 1 }} · {{ pageLabels.length }} label
                    </p>
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            v-for="(label, labelIndex) in pageLabels"
                            :key="labelIndex"
                            class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-950"
                            style="font-family: 'Fira Code', monospace"
                        >
                            <div class="break-words bg-emerald-600 px-2 py-1 text-center text-[9px] font-bold leading-tight text-white">
                                {{ String(label.name || '').toUpperCase() }}
                            </div>
                            <div class="flex-1 px-2 py-1 text-[8px] leading-snug text-gray-500 dark:text-gray-400">
                                <div v-for="(line, i) in String(label.description || '-').toUpperCase().split('\n')" :key="i">
                                    {{ line }}
                                </div>
                            </div>
                            <div v-if="label.warranty" class="px-2 py-0.5 text-center text-[7px] italic text-gray-400 dark:text-gray-500">
                                Garansi {{ label.warranty }}
                            </div>
                            <div class="bg-emerald-600 px-2 py-1 text-center text-[9px] font-bold text-white">
                                RP {{ Number(label.price || 0).toLocaleString('id-ID') }},-
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppModal :show="showBasket" title="Basket" size="md" @close="showBasket = false">
            <div v-if="!basket.length" class="py-10 text-center">
                <p class="text-sm text-gray-400 dark:text-gray-500">Basket kosong</p>
            </div>
            <div v-else class="-mx-5 divide-y divide-gray-100 dark:divide-gray-800">
                <div v-for="row in basket" :key="row.id" class="flex items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ row.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatRupiah(row.price) }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="row.qty = Math.max(1, row.qty - 1)"
                        >
                            <Minus class="h-3 w-3" />
                        </button>
                        <input
                            v-model.number="row.qty"
                            type="number"
                            min="1"
                            class="w-10 rounded-lg border border-gray-200 bg-gray-100 px-1 py-0.5 text-center text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="row.qty++"
                        >
                            <Plus class="h-3 w-3" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-400/10"
                        @click="remove(row.id)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ totalLabels }} label · {{ pages.length }} halaman</p>
                <div class="flex gap-2">
                    <AppButton variant="ghost" size="sm" @click="clear(); showBasket = false">Hapus semua</AppButton>
                    <AppButton size="sm" :disabled="!totalLabels" @click="handlePrint(); showBasket = false">Print</AppButton>
                </div>
            </div>
        </AppModal>

        <AppModal :show="showManual" title="Tambah Manual" size="sm" @close="showManual = false">
            <form class="space-y-4" @submit.prevent="saveManual">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nama Item *</label>
                    <input v-model="manualForm.name" type="text" required placeholder="Nama produk" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Harga *</label>
                    <input v-model.number="manualForm.price" type="number" min="0" required placeholder="0" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Deskripsi / Spek</label>
                    <textarea v-model="manualForm.description" rows="3" placeholder="CPU, RAM, Storage..." class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500" />
                </div>
                <div class="flex gap-3 pt-1">
                    <AppButton variant="secondary" class="flex-1" @click="showManual = false">Batal</AppButton>
                    <AppButton type="submit" class="flex-1">Tambah</AppButton>
                </div>
            </form>
        </AppModal>
    </AppLayout>
</template>
