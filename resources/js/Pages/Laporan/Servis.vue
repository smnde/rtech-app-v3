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
    { label: 'Total Servis', value: props.summary.count },
    { label: 'Selesai', value: props.summary.done },
    { label: 'Proses', value: props.summary.process },
    { label: 'Pending', value: props.summary.pending },
]);

const columns = [
    { key: 'date',            label: 'Masuk' },
    { key: 'customer',        label: 'Customer' },
    { key: 'item_name',       label: 'Perangkat' },
    { key: 'complaint',       label: 'Keluhan' },
    { key: 'estimated_cost',  label: 'Estimasi' },
    { key: 'status',          label: 'Status' },
];

const statusColor = {
    RECEIVED: 'gray',
    CHECKED: 'blue',
    CONFIRMED: 'blue',
    'WAITING CONFIRMATION': 'yellow',
    'ON PROCESS': 'purple',
    PENDING: 'yellow',
    CANCEL: 'red',
    DONE: 'green',
    TAKEN: 'green',
};
</script>

<template>
    <Head title="Laporan Servis" />

    <LaporanPageBase
        page-title="Laporan Servis"
        description="Laporan seluruh transaksi servis per hari."
        route-name="laporan.servis"
        :filters="filters"
        :columns="columns"
        :rows="rows"
        :stats="stats"
    >
        <template #cell-estimated_cost="{ value }">
            <span class="font-semibold text-gray-900 dark:text-white">{{ value || '—' }}</span>
        </template>
        <template #cell-status="{ value }">
            <AppBadge :color="statusColor[value] ?? 'gray'" size="sm" dot>{{ value }}</AppBadge>
        </template>
    </LaporanPageBase>
</template>
