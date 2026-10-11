import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useStockFilter(routeName, filters, filterKeys) {
    const form = ref({
        search: filters.search ?? '',
        sort: filters.sort ?? 'asc',
        ...Object.fromEntries(filterKeys.map((key) => [key, filters[key] ?? ''])),
    });

    const hasActiveFilter = computed(() =>
        [...filterKeys, 'search'].some((key) => form.value[key] !== ''),
    );

    const toggleSort = () => {
        form.value.sort = form.value.sort === 'asc' ? 'desc' : 'asc';
    };

    const applyFilter = () => {
        const query = { sort: form.value.sort };
        for (const key of ['search', ...filterKeys]) {
            query[key] = form.value[key] || undefined;
        }
        router.get(route(routeName), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    let searchTimer = null;
    watch(
        () => form.value.search,
        () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilter, 300);
        },
    );
    watch(
        () => [...filterKeys, 'sort'].map((key) => form.value[key]),
        applyFilter,
    );

    const resetFilter = () => {
        form.value = {
            search: '',
            sort: 'asc',
            ...Object.fromEntries(filterKeys.map((key) => [key, ''])),
        };
        applyFilter();
    };

    return { form, hasActiveFilter, toggleSort, resetFilter };
}
