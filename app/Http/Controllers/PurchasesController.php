<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\PurchasesResource;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Supplier;
use App\Support\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class PurchasesController extends Controller
{
    public function index(Request $request): Response
    {
        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'customer:id,name'])
            ->when($request->search, fn ($query, $search) => $query->where('invoice', 'like', "%{$search}%"))
            ->when($request->filled('status'), fn ($query) => $query->where('payment_status', $request->string('status')))
            ->when($request->filled('po_type'), fn ($query) => $query->where('po_type', $request->string('po_type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('to')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Transaksi/Pembelian', [
            'purchases' => PurchasesResource::collection($purchases),
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'po_type' => $request->po_type,
                'from' => $request->from,
                'to' => $request->to,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $cart = Cart::activeFor($request->user(), 'purchase')->load(['customer:id,name', 'items']);
        $heldCarts = Cart::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'purchase')
            ->where('status', 'held')
            ->with(['customer:id,name', 'items'])
            ->latest()
            ->get();

        return inertia('Transaksi/PembelianCreate', [
            'products' => $this->purchasableProducts(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'is_member']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name as label']),
            'cart' => new CartResource($cart),
            'heldCarts' => CartResource::collection($heldCarts),
        ]);
    }

    /**
     * @return list<array{item_type: string, id: int, label: string, code: string, buy_price: float}>
     */
    private function purchasableProducts(): array
    {
        $products = [];

        foreach (StockItem::types() as $type => $model) {
            $model::query()
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'buy_price'])
                ->each(function ($item) use ($type, &$products) {
                    $products[] = [
                        'item_type' => $type,
                        'id' => $item->id,
                        'label' => $item->name,
                        'code' => $item->sku,
                        'buy_price' => (float) $item->buy_price,
                    ];
                });
        }

        return $products;
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
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
                $lineSubtotal = $qty * $price;

                $subtotal += $lineSubtotal;
                $lines[] = [
                    'item_type' => $item['item_type'],
                    'item_id' => $stockItem->id,
                    'qty' => $qty,
                    'buy_price' => $price,
                    'subtotal' => $lineSubtotal,
                ];

                $stockItem->increment('stock', $qty);
            }

            $total = $subtotal;

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'buy_from_reference' => $data['buy_from_reference'] ?? null,
                'total' => $total,
                'payment_status' => $data['payment_status'],
                'po_type' => $data['po_type'],
                'paid_at' => $data['paid_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    ...$line,
                ]);
            }

            $activeCart = Cart::activeFor($request->user(), 'purchase');
            $activeCart->items()->delete();
            $activeCart->update(['customer_id' => null, 'total' => 0, 'item_count' => 0]);
        });

        return redirect()->route('transaksi.pembelian')->with('success', 'Pembelian berhasil disimpan.');
    }
}
