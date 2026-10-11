import { usePriceTagBasket } from '@/composables/usePriceTagBasket';

const formatSpecification = (specification) => {
    const lines = Object.entries(specification ?? {})
        .filter(([, value]) => value !== null && value !== undefined && `${value}`.trim() !== '')
        .map(([key, value]) => `${key.charAt(0).toUpperCase()}${key.slice(1)}: ${value}`);
    return lines.length ? lines.join('\n') : '-';
};

export function useLaptopPriceTag() {
    const { add } = usePriceTagBasket('mt_price_tag_basket', 9);

    const addLaptopToPriceTag = (laptop) => {
        add({
            id: `laptop-${laptop.id}`,
            code: laptop.sku,
            name: laptop.name,
            price: Number(laptop.sell_price) || 0,
            description: formatSpecification(laptop.specification),
            warranty: laptop.warranty,
        });
    };

    return { addLaptopToPriceTag };
}
