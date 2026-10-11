<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

/**
 * AppPagination — kontrol paginasi untuk data Laravel LengthAwarePaginator.
 * Menerima objek paginator utuh (dengan data, links, from, to, total).
 */
const props = defineProps({
    paginator: { type: Object, required: true },
});

const prevLink = computed(() => props.paginator.links?.[0]);
const nextLink = computed(() => props.paginator.links?.[props.paginator.links.length - 1]);
const pageLinks = computed(() => (props.paginator.links ?? []).slice(1, -1));
</script>

<template>
    <div
        v-if="paginator.total > 0"
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-center text-xs text-gray-500 dark:text-gray-400 sm:text-left">
            Menampilkan
            <span class="font-medium text-gray-700 dark:text-gray-200">{{ paginator.from }}–{{ paginator.to }}</span>
            dari
            <span class="font-medium text-gray-700 dark:text-gray-200">{{ paginator.total }}</span>
            data
        </p>

        <nav class="flex items-center justify-center gap-1" aria-label="Paginasi">
            <component
                :is="prevLink?.url ? Link : 'span'"
                :href="prevLink?.url"
                :class="[
                    'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300',
                    prevLink?.url ? 'hover:bg-gray-100 dark:hover:bg-gray-800' : 'opacity-40 cursor-not-allowed',
                ]"
                aria-label="Halaman sebelumnya"
            >
                <ChevronLeft class="h-4 w-4" />
            </component>

            <template v-for="link in pageLinks" :key="link.label">
                <component
                    :is="link.url ? Link : 'span'"
                    :href="link.url"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-medium transition-colors',
                        link.active
                            ? 'bg-primary-600 text-white dark:bg-primary-500'
                            : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                        !link.url && !link.active ? 'opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </template>

            <component
                :is="nextLink?.url ? Link : 'span'"
                :href="nextLink?.url"
                :class="[
                    'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300',
                    nextLink?.url ? 'hover:bg-gray-100 dark:hover:bg-gray-800' : 'opacity-40 cursor-not-allowed',
                ]"
                aria-label="Halaman berikutnya"
            >
                <ChevronRight class="h-4 w-4" />
            </component>
        </nav>
    </div>
</template>
