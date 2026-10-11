<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';

const props = defineProps({
    pageTitle:   { type: String, required: true },
    description: { type: String, default: '' },
    columns:     { type: Array, required: true },
    rows:        { type: Array, default: () => [] },
    stats:       { type: Array, default: () => [] },
    routeName:   { type: String, required: true },
    filters:     { type: Object, default: () => ({}) },
});

const period = ref({ from: props.filters.from ?? '', to: props.filters.to ?? '' });

const hasActivePeriod = computed(() => period.value.from !== '' || period.value.to !== '');

const resetPeriod = () => {
    period.value = { from: '', to: '' };
    applyPeriod();
};

const applyPeriod = () => {
    router.get(route(props.routeName), {
        from: period.value.from || undefined,
        to: period.value.to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

watch(() => props.filters, (value) => {
    period.value = { from: value.from ?? '', to: value.to ?? '' };
});
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="flex h-full flex-col">
        <AppPageHeader :title="pageTitle" :description="description" class="shrink-0">
            <template #actions>
                <div class="hidden sm:block w-72">
                    <AppDateRangePicker v-model="period" @update:model-value="applyPeriod" />
                </div>
                <AppFilterModal :active="hasActivePeriod" @reset="resetPeriod">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode</label>
                        <AppDateRangePicker v-model="period" @update:model-value="applyPeriod" />
                    </div>
                </AppFilterModal>
                <AppButton variant="outline" size="sm">
                    <Download class="h-4 w-4" />
                    Export
                </AppButton>
            </template>
        </AppPageHeader>

        <div v-if="stats.length" class="shrink-0 grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <AppCard v-for="stat in stats" :key="stat.label" class="flex flex-col justify-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ stat.label }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ stat.value }}</p>
            </AppCard>
        </div>

        <AppTable :is-empty="rows.length === 0" class="flex-1 min-h-0">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800/60">
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap"
                    >
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr
                    v-for="(row, i) in rows"
                    :key="i"
                    class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                            {{ row[col.key] }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </AppTable>
        </div>
    </AppLayout>
</template>
