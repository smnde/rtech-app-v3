import { usePriceTagBasket } from '@/composables/usePriceTagBasket';

const formatSpecification = (specification) => {
    const lines = Object.entries(specification ?? {})
        .filter(([, value]) => value !== null && value !== undefined && `${value}`.trim() !== '')
        .map(([key, value]) => `${key.charAt(0).toUpperCase()}${key.slice(1)}: ${value}`);
    return lines.length ? lines.join('\n') : '-';
};

// Laptop & gadget punya field sama (sku, name, sell_price, specification, warranty).
export function useItemPriceTag() {
    const { add } = usePriceTagBasket('mt_price_tag_basket', 9);

    const addItemToPriceTag = (item) => {
        add({
            id: String(item.sku ?? item.id),
            code: item.sku,
            name: item.name,
            price: Number(item.sell_price) || 0,
            description: formatSpecification(item.specification),
            warranty: item.warranty,
        });
    };

    return { addItemToPriceTag };
}
