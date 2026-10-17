<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
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
    { key: 'invoice',   label: 'No. Invoice' },
    { key: 'date',      label: 'Tanggal' },
    { key: 'item_name', label: 'Item' },
    { key: 'qty',       label: 'Qty' },
    { key: 'subtotal',  label: 'Subtotal' },
];
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
        export-pdf
    >
        <template #cell-invoice="{ row }">
            <Link
                :href="route('transaksi.pembelian.show', row.purchase_id)"
                class="font-mono text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
            >{{ row.invoice }}</Link>
        </template>
        <template #cell-subtotal="{ value }">
            <span class="font-semibold text-gray-900 dark:text-white">{{ formatRupiah(value) }}</span>
        </template>
    </LaporanPageBase>
</template>
