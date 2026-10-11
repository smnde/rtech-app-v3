<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import LaporanPageBase from '@/Pages/Shared/LaporanPageBase.vue';

const props = defineProps({
    rows:    { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const formatRupiah = (value) => `Rp ${Number(value).toLocaleString('id-ID')}`;

const stats = computed(() => [
    { label: 'Total Pengeluaran', value: props.summary.count },
    { label: 'Total Nominal', value: formatRupiah(props.summary.total) },
]);

const columns = [
    { key: 'date',        label: 'Tanggal' },
    { key: 'category',    label: 'Kategori' },
    { key: 'description', label: 'Keterangan' },
    { key: 'amount',      label: 'Nominal' },
    { key: 'user_name',   label: 'Dicatat oleh' },
];
</script>

<template>
    <Head title="Laporan Pengeluaran" />

    <LaporanPageBase
        page-title="Laporan Pengeluaran"
        description="Laporan pengeluaran operasional per hari."
        route-name="laporan.pengeluaran"
        :filters="filters"
        :columns="columns"
        :rows="rows"
        :stats="stats"
    >
        <template #cell-amount="{ value }">
            <span class="font-semibold text-gray-900 dark:text-white">{{ formatRupiah(value) }}</span>
        </template>
    </LaporanPageBase>
</template>
