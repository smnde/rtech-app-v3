<script setup>
import { computed } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import ItemLogBadge from './ItemLogBadge.vue';

const props = defineProps({
    log: { type: Object, required: true },
    canDelete: { type: Boolean, default: false },
});

const emit = defineEmits(['delete']);

const SPEC_LABELS = { cpu: 'CPU', gpu: 'GPU', memory: 'Memory', storage: 'Storage', display: 'Display' };

const specChanges = computed(() => {
    if (!props.log.spec_after) return [];
    return Object.entries(SPEC_LABELS)
        .filter(([key]) => props.log.spec_after[key] !== undefined)
        .map(([key, label]) => ({
            label,
            before: props.log.spec_before?.[key] ?? null,
            after: props.log.spec_after[key],
        }));
});

const formatDateTime = (value) => (value
    ? new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');
</script>

<template>
    <div class="relative">
        <span class="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-indigo-500"></span>

        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <ItemLogBadge :type="log.type" />
                <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ formatDateTime(log.created_at) }}</span>
            </div>
            <button v-if="canDelete" type="button" title="Hapus log"
                class="shrink-0 rounded-md p-1 text-gray-400 transition-colors hover:bg-red-500/10 hover:text-red-500"
                @click="emit('delete', log)">
                <Trash2 class="h-3.5 w-3.5" />
            </button>
        </div>

        <p class="mt-1.5 text-sm text-gray-800 dark:text-gray-200">{{ log.description }}</p>

        <div v-if="specChanges.length" class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
            <p class="bg-gray-50 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                Perubahan Spesifikasi
            </p>
            <table class="w-full text-xs">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="change in specChanges" :key="change.label">
                        <td class="w-20 px-3 py-1.5 font-medium text-gray-500 dark:text-gray-400">{{ change.label }}</td>
                        <td class="px-3 py-1.5 text-gray-400 line-through dark:text-gray-500">{{ change.before || '—' }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200">{{ change.after }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="log.status_after" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Status:
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ log.status_before ?? '—' }}</span>
            →
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ log.status_after }}</span>
        </p>

        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            {{ log.user?.name ?? 'Unknown' }}
            <template v-if="log.assignee"> · Dikerjakan oleh {{ log.assignee.name }}</template>
        </p>
    </div>
</template>
