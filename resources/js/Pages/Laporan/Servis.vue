<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import LaporanPageBase from '@/Pages/Shared/LaporanPageBase.vue';

const props = defineProps({
    rows:    { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const stats = computed(() => [
    { label: 'Total Servis Diambil', value: props.summary.count },
]);

const columns = [
    { key: 'invoice', label: 'No. Invoice' },
    { key: 'date',    label: 'Tanggal' },
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
    </LaporanPageBase>
</template>
