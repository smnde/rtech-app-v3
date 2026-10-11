<?php

namespace App\Http\Controllers;

use App\Support\StockItem;
use Inertia\Response;

class PriceTagController extends Controller
{
    public function index(): Response
    {
        $items = [];

        foreach (StockItem::types() as $type => $model) {
            $model::query()
                ->where('sell_price', '>', 0)
                ->orderBy('name')
                ->get(array_filter(['id', 'name', 'sku', 'sell_price', 'warranty', $type === 'laptop' || $type === 'gadget' ? 'specification' : null]))
                ->each(function ($item) use ($type, &$items) {
                    $items[] = [
                        'id' => $type.'-'.$item->id,
                        'name' => $item->name,
                        'code' => $item->sku,
                        'sell_price' => (float) $item->sell_price,
                        'warranty' => $item->warranty,
                        'specification' => $this->specificationText($item->specification ?? null),
                    ];
                });
        }

        return inertia('PriceTag/Index', [
            'items' => $items,
        ]);
    }

    /**
     * @param  array<string, string>|null  $specification
     */
    private function specificationText(?array $specification): ?string
    {
        if (! $specification) {
            return null;
        }

        return collect($specification)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $key) => ucfirst((string) $key).': '.$value)
            ->implode("\n");
    }
}
