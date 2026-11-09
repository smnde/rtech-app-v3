/**
 * Rincian biaya dari tahap servis yang statusnya CONFIRMED.
 * Biaya 0 dibuang supaya tidak ikut tercatat di kas.
 */
export function servisConfirmedItems(stages) {
    return (stages ?? [])
        .filter((stage) => stage.status === 'CONFIRMED')
        .flatMap((stage) => stage.items ?? [])
        .map((item) => ({ label: item.label, cost: Number(item.cost ?? 0) }))
        .filter((item) => item.cost > 0);
}
