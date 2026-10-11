import { ref, watch, computed } from 'vue';

export function usePriceTagBasket(storageKey, perPage = null) {
    function loadJSON(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
    }

    const basket = ref(loadJSON(storageKey, []));

    watch(basket, (value) => localStorage.setItem(storageKey, JSON.stringify(value)), { deep: true });

    const labels = computed(() =>
        basket.value.flatMap((row) => Array.from({ length: Number(row.qty || 1) }, () => row))
    );

    const pages = computed(() => {
        if (!perPage) return [labels.value];
        const out = [];
        for (let i = 0; i < labels.value.length; i += perPage) out.push(labels.value.slice(i, i + perPage));
        return out;
    });

    const totalLabels = computed(() => labels.value.length);

    function add(entry) {
        const index = basket.value.findIndex((row) => row.id === entry.id);
        if (index >= 0) {
            basket.value[index].qty++;
        } else {
            basket.value.push({ ...entry, qty: 1 });
        }
    }

    function remove(id) {
        basket.value = basket.value.filter((row) => row.id !== id);
    }

    function clear() {
        basket.value = [];
    }

    return { basket, pages, totalLabels, add, remove, clear };
}
