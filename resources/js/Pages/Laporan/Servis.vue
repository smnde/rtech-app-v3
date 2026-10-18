<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LaporanPageBase from '@/Pages/Shared/LaporanPageBase.vue';

const props = defineProps({
    rows:    { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const stats = computed(() => [
    { label: 'Total Servis Diambil', value: props.summary.count },
    { label: 'Total Biaya', value: formatRupiah(props.summary.total) },
    { label: 'Belum Lunas', value: props.summary.unpaid ?? 0 },
]);

const columns = [
    { key: 'invoice', label: 'No. Invoice' },
    { key: 'date',    label: 'Tanggal' },
    { key: 'customer', label: 'Customer' },
    { key: 'label',   label: 'Kendala' },
    { key: 'cost',    label: 'Biaya' },
];
</script>

<template>
    <Head title="Laporan Servis" />

    <LaporanPageBase
        page-title="Laporan Servis"
        description="Laporan servis yang sudah diambil customer per hari."
        route-name="laporan.servis"
        :filters="filters"
        :columns="columns"
        :rows="rows"
        :stats="stats"
        export-pdf
    >
        <template #cell-invoice="{ row }">
            <Link
                :href="route('servis.show', row.id)"
                class="font-mono text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
            >{{ row.invoice }}</Link>
        </template>
        <template #cell-cost="{ row }">
            <span class="tabular-nums">{{ formatRupiah(row.cost) }}</span>
        </template>
        <template #cell-customer="{ row }">
            <span class="inline-flex items-center gap-2">
                {{ row.customer }}
                <span v-if="!row.paid" class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Belum lunas</span>
            </span>
        </template>
    </LaporanPageBase>
</template>
