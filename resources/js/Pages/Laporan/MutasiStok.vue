<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBadge from '@/Components/UI/AppBadge.vue';
import LaporanPageBase from '@/Pages/Shared/LaporanPageBase.vue';

const props = defineProps({
    rows:    { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const stats = computed(() => [
    { label: 'Total Mutasi', value: props.summary.count },
    { label: 'Stok Masuk', value: `+${props.summary.in}` },
    { label: 'Stok Keluar', value: `-${props.summary.out}` },
]);

const columns = [
    { key: 'date',         label: 'Tanggal' },
    { key: 'item_name',    label: 'Item' },
    { key: 'type',         label: 'Tipe' },
    { key: 'status',       label: 'Status' },
    { key: 'quantity',     label: 'Qty' },
    { key: 'stock',        label: 'Stok' },
    { key: 'reference',    label: 'Referensi' },
    { key: 'user_name',    label: 'Oleh' },
];

const TYPE_COLOR = { in: 'green', out: 'red', adjustment: 'yellow' };
const TYPE_LABEL = { in: 'Masuk', out: 'Keluar', adjustment: 'Penyesuaian' };
const STATUS_LABEL = { NONE: 'Biasa', BONUS: 'Bonus', DEBT: 'Hutang', 'SOLD OUT': 'Sold Out', REFUND: 'Refund' };
</script>

<template>
    <Head title="Laporan Mutasi Stok" />

    <LaporanPageBase
        page-title="Laporan Mutasi Stok"
        description="Laporan keluar masuk stok per hari."
        route-name="laporan.mutasi"
        :filters="filters"
        :columns="columns"
        :rows="rows"
        :stats="stats"
    >
        <template #cell-type="{ value }">
            <AppBadge :color="TYPE_COLOR[value] ?? 'gray'" size="sm" dot>{{ TYPE_LABEL[value] ?? value }}</AppBadge>
        </template>
        <template #cell-status="{ value, row }">
            <span class="text-sm text-gray-600 dark:text-gray-300">{{ row.type === 'adjustment' ? '-' : (STATUS_LABEL[value] ?? value) }}</span>
        </template>
        <template #cell-stock="{ row }">
            <span class="tabular-nums">{{ row.stock_before }} → {{ row.stock_after }}</span>
        </template>
    </LaporanPageBase>
</template>
