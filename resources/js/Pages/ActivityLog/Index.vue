<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search, ScrollText } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppPagination from '@/Components/UI/AppPagination.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppTable from '@/Components/UI/AppTable.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppDateRangePicker from '@/Components/UI/AppDateRangePicker.vue';
import AppFilterModal from '@/Components/UI/AppFilterModal.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    logs:    { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    modules: { type: Array, default: () => [] },
    actions: { type: Array, default: () => [] },
    users:   { type: Array, default: () => [] },
});

const page  = usePage();
const toast = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const rows = computed(() => props.logs.data);

const actionMap = computed(() =>
    Object.fromEntries(props.actions.map((action) => [action.id, action])),
);

const moduleLabel = (module) =>
    props.modules.find((option) => option.id === module)?.label ?? module;

const actionLabel = (action) => actionMap.value[action]?.label ?? action;
const actionColor = (action) => actionMap.value[action]?.color ?? 'gray';

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const defaultRange = () => ({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const filterForm = reactive({
    search:  props.filters.search ?? '',
    module:  props.filters.module ?? 'all',
    action:  props.filters.action ?? 'all',
    user_id: props.filters.user_id ?? 'all',
    period:  defaultRange(),
});

const moduleOptions = computed(() => [
    { id: 'all', label: 'Semua modul' },
    ...props.modules,
]);

const actionOptions = computed(() => [
    { id: 'all', label: 'Semua aksi' },
    ...props.actions.map((action) => ({ id: action.id, label: action.label })),
]);

const userOptions = computed(() => [
    { id: 'all', label: 'Semua user' },
    ...props.users,
]);

const hasActiveFilter = computed(() =>
    filterForm.module !== 'all'
    || filterForm.action !== 'all'
    || filterForm.user_id !== 'all'
    || !!filterForm.period.from
    || !!filterForm.period.to,
);

const applyFilter = () => {
    router.get(route('activity-log.index'), {
        search:  filterForm.search || undefined,
        module:  filterForm.module === 'all' ? undefined : filterForm.module,
        action:  filterForm.action === 'all' ? undefined : filterForm.action,
        user_id: filterForm.user_id === 'all' ? undefined : filterForm.user_id,
        from:    filterForm.period.from || undefined,
        to:      filterForm.period.to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilter = () => {
    filterForm.module = 'all';
    filterForm.action = 'all';
    filterForm.user_id = 'all';
    filterForm.period = { from: '', to: '' };
    applyFilter();
};

let searchTimer = null;

watch(
    () => filterForm.search,
    (value) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            router.get(route('activity-log.index'), {
                search: value || undefined,
                module: filterForm.module === 'all' ? undefined : filterForm.module,
                action: filterForm.action === 'all' ? undefined : filterForm.action,
                user_id: filterForm.user_id === 'all' ? undefined : filterForm.user_id,
                from: filterForm.period.from || undefined,
                to: filterForm.period.to || undefined,
            }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    },
);
</script>

<template>
    <Head title="Log Aktivitas" />

    <AppLayout title="Log Aktivitas">
        <div class="flex h-full flex-col">
            <AppPageHeader title="Log Aktivitas" description="Riwayat perubahan data dan aktivitas login user." class="shrink-0">
                <template #actions>
                    <AppFilterModal :active="hasActiveFilter" @reset="resetFilter">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periode</label>
                            <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Modul</label>
                            <AppDropdown v-model="filterForm.module" :options="moduleOptions" @update:model-value="applyFilter" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Aksi</label>
                            <AppDropdown v-model="filterForm.action" :options="actionOptions" @update:model-value="applyFilter" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
                            <AppDropdown v-model="filterForm.user_id" :options="userOptions" @update:model-value="applyFilter" />
                        </div>
                    </AppFilterModal>
                </template>
                <template #search>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Cari aktivitas..."
                            class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-4 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 sm:w-64 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </template>
            </AppPageHeader>

            <AppCard class="mb-5 hidden sm:block">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full sm:w-80">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Periode</label>
                        <AppDateRangePicker v-model="filterForm.period" @update:model-value="applyFilter" />
                    </div>
                    <div class="w-48">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Modul</label>
                        <AppDropdown v-model="filterForm.module" :options="moduleOptions" @update:model-value="applyFilter" />
                    </div>
                    <div class="w-44">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Aksi</label>
                        <AppDropdown v-model="filterForm.action" :options="actionOptions" @update:model-value="applyFilter" />
                    </div>
                    <div class="w-48">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">User</label>
                        <AppDropdown v-model="filterForm.user_id" :options="userOptions" @update:model-value="applyFilter" />
                    </div>
                </div>
            </AppCard>

            <!-- DESKTOP -->
            <AppTable :is-empty="rows.length === 0" empty-message="Belum ada aktivitas." class="hidden sm:flex flex-1 min-h-0">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800/60">
                        <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Waktu</th>
                        <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                        <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aksi</th>
                        <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Modul</th>
                        <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Keterangan</th>
                        <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="log in rows" :key="log.id" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime(log.created_at) }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ log.user_name }}</td>
                        <td class="px-5 py-3">
                            <AppBadge :color="actionColor(log.action)" size="sm" dot>{{ actionLabel(log.action) }}</AppBadge>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ moduleLabel(log.module) }}</td>
                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ log.description }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-400">{{ log.ip ?? '—' }}</td>
                    </tr>
                </tbody>
                <template #pagination>
                    <AppPagination :paginator="logs" />
                </template>
            </AppTable>

            <!-- MOBILE -->
            <div class="scrollbar-styled sm:hidden flex-1 min-h-0 overflow-y-auto space-y-2">
                <div v-if="rows.length === 0" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 rounded-full bg-gray-100 p-4 dark:bg-gray-800">
                        <ScrollText class="h-6 w-6 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas.</p>
                </div>

                <div
                    v-for="log in rows"
                    :key="log.id"
                    class="rounded-xl border border-gray-200 bg-white px-3.5 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 flex-1 text-sm font-medium text-gray-900 dark:text-white">{{ log.description }}</p>
                        <AppBadge :color="actionColor(log.action)" size="sm" dot>{{ actionLabel(log.action) }}</AppBadge>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ log.user_name }}</span>
                        <span>·</span>
                        <span>{{ moduleLabel(log.module) }}</span>
                        <span>·</span>
                        <span>{{ formatDateTime(log.created_at) }}</span>
                    </div>
                </div>
            </div>

            <div v-if="logs.total > 0" class="mt-4 sm:hidden">
                <AppPagination :paginator="logs" />
            </div>
        </div>
    </AppLayout>
</template>
