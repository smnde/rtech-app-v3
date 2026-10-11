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

const formatRupiah = (value) => `Rp ${Number(value).toLocaleString('id-ID')}`;

const stats = computed(() => [
    { label: 'Total Pembelian', value: props.summary.count },
    { label: 'Total Nominal', value: formatRupiah(props.summary.total) },
]);

const columns = [
    { key: 'invoice',        label: 'No. Invoice' },
    { key: 'date',           label: 'Tanggal' },
    { key: 'source',         label: 'Sumber' },
    { key: 'po_type',        label: 'Jenis' },
    { key: 'total',          label: 'Total' },
    { key: 'payment_status', label: 'Status' },
];

const statusColor = { lunas: 'green', hutang: 'yellow' };
</script>

<template>
    <Head title="Laporan Pembelian" />

    <LaporanPageBase
        page-title="Laporan Pembelian"
        description="Laporan seluruh transaksi pembelian per hari."
        route-name="laporan.pembelian"
        :filters="filters"
        :columns="columns"
        :rows="rows"
        :stats="stats"
    >
        <template #cell-total="{ value }">
            <span class="font-semibold text-gray-900 dark:text-white">{{ formatRupiah(value) }}</span>
        </template>
        <template #cell-payment_status="{ value }">
            <AppBadge :color="statusColor[value] ?? 'gray'" size="sm" dot>{{ value }}</AppBadge>
        </template>
    </LaporanPageBase>
</template>
