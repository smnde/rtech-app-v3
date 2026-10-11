<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\SalesResource;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalesDetail;
use App\Support\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->with('customer:id,name')
            ->when($request->search, fn ($query, $search) => $query->where('invoice', 'like', "%{$search}%"))
            ->when($request->filled('status'), fn ($query) => $query->where('payment_status', $request->string('status')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('to')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Transaksi/Penjualan', [
            'sales' => SalesResource::collection($sales),
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'from' => $request->from,
                'to' => $request->to,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $cart = Cart::activeFor($request->user(), 'sale')->load(['customer:id,name', 'items']);
        $heldCarts = Cart::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'sale')
            ->where('status', 'held')
            ->with(['customer:id,name', 'items'])
            ->latest()
            ->get();

        return inertia('Transaksi/PenjualanCreate', [
            'products' => $this->sellableProducts(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'is_member']),
            'cart' => new CartResource($cart),
            'heldCarts' => CartResource::collection($heldCarts),
        ]);
    }

    /**
     * @param  array<string, string>|null  $specification
     */
    private function shortSpec(?array $specification): ?string
    {
        $parts = collect(['cpu', 'memory', 'storage'])
            ->map(fn (string $key) => $specification[$key] ?? null)
            ->filter(fn ($value) => filled($value))
            ->all();

        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * @return list<array{item_type: string, id: int, label: string, code: string, sell_price: float, spec: ?string}>
     */
    private function sellableProducts(): array
    {
        $products = [];

        foreach (StockItem::types() as $type => $model) {
            $hasSpecification = in_array($type, ['laptop', 'gadget'], true);
            $model::query()
                ->where('stock', '>', 0)
                ->where('sell_price', '>', 0)
                ->when($type === 'laptop', fn ($query) => $query->whereIn('status', ['SIAP JUAL', 'DISPLAY']))
                ->orderBy('name')
                ->get(array_filter(['id', 'name', 'sku', 'sell_price', $hasSpecification ? 'specification' : null]))
                ->each(function ($item) use ($type, $hasSpecification, &$products) {
                    $products[] = [
                        'item_type' => $type,
                        'id' => $item->id,
                        'label' => $item->name,
                        'code' => $item->sku,
                        'sell_price' => (float) $item->sell_price,
                        'spec' => $hasSpecification ? $this->shortSpec($item->specification) : null,
                    ];
                });
        }

        return $products;
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $subtotal = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $stockItem = StockItem::modelFor($item['item_type'])::query()
                    ->lockForUpdate()
                    ->findOrFail($item['item_id']);

                $qty = (int) $item['qty'];
                $price = (float) $item['price'];
                $lineDiscount = (float) ($item['discount'] ?? 0);
                $lineSubtotal = max(0, ($qty * $price) - $lineDiscount);

                if ($stockItem->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$stockItem->name} tidak cukup (sisa {$stockItem->stock}).",
                    ]);
                }

                $subtotal += $lineSubtotal;
                $lines[] = [
                    'item_type' => $item['item_type'],
                    'item_id' => $stockItem->id,
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                ];

                $stockItem->decrement('stock', $qty);
            }

            $discount = (float) ($data['discount'] ?? 0);
            $total = max(0, $subtotal - $discount);

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'payment_status' => $data['payment_status'],
                'dp_amount' => $data['payment_status'] === 'keep' ? $data['dp_amount'] : null,
                'paid_at' => $data['payment_status'] === 'keep' ? $data['paid_at'] : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                SalesDetail::create([
                    'sales_id' => $sale->id,
                    ...$line,
                ]);
            }

            $activeCart = Cart::activeFor($request->user(), 'sale');
            $activeCart->items()->delete();
            $activeCart->update(['customer_id' => null, 'total' => 0, 'item_count' => 0]);
        });

        return redirect()->route('transaksi.penjualan')->with('success', 'Penjualan berhasil disimpan.');
    }
}
