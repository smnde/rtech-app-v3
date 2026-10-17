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
use App\Support\StockMovementRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class PurchasesController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $from  = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to    = $request->filled('to')   ? $request->string('to')->toString()   : $today;

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'customer:id,name'])
            ->when($request->search, fn ($query, $search) => $query->where('invoice', 'like', "%{$search}%"))
            ->when($request->filled('status'), fn ($query) => $query->where('payment_status', $request->string('status')))
            ->when($request->filled('order_status'), fn ($query) => $query->where('status', $request->string('order_status')))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Transaksi/Pembelian', [
            'purchases' => PurchasesResource::collection($purchases),
            'filters' => [
                'search'       => $request->search,
                'status'       => $request->status,
                'order_status' => $request->order_status,
                'from'         => $from,
                'to'           => $to,
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
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'name as label', 'phone']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'is_member']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name as label']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name as label']),
            'cart' => new CartResource($cart),
            'heldCarts' => CartResource::collection($heldCarts),
        ]);
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['user:id,name', 'supplier:id,name,phone', 'customer:id,name,phone', 'details.item']);

        return inertia('Transaksi/PembelianDetail', [
            'purchase' => [
                'invoice' => $purchase->invoice,
                'date' => $purchase->created_at->toDateTimeString(),
                'cashier' => $purchase->user?->name,
                'supplier' => $purchase->supplier ? [
                    'id' => $purchase->supplier->id,
                    'name' => $purchase->supplier->name,
                    'phone' => $purchase->supplier->phone,
                ] : null,
                'customer' => $purchase->customer ? [
                    'id' => $purchase->customer->id,
                    'name' => $purchase->customer->name,
                    'phone' => $purchase->customer->phone,
                ] : null,
                'buy_from_reference' => $purchase->buy_from_reference,
                'status' => $purchase->status,
                'payment_status' => $purchase->payment_status,
                'total' => (float) $purchase->total,
                'paid_at' => $purchase->paid_at?->toDateTimeString(),
                'notes' => $purchase->notes,
                'items' => $purchase->details->map(fn ($detail) => [
                    'item_name' => $detail->item?->name,
                    'item_code' => $detail->item?->sku,
                    'qty' => $detail->qty,
                    'price' => (float) $detail->buy_price,
                    'subtotal' => (float) $detail->subtotal,
                    'specification' => $detail->item?->specification ?: null,
                    'accessories' => $detail->item?->accessories ?: [],
                    'features' => $detail->item?->features ?: [],
                    'serial_number' => $detail->item?->serialnumber,
                ])->values(),
            ],
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
        $fromSupplier = filled($data['supplier_id'] ?? null);
        $status = $fromSupplier ? $data['status'] : 'done';

        DB::transaction(function () use ($data, $request, $status) {
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
                    'stock_item' => $stockItem,
                ];
            }

            $total = $subtotal;

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'buy_from_reference' => $data['buy_from_reference'] ?? null,
                'total' => $total,
                'payment_status' => $data['payment_status'],
                'status' => $status,
                'paid_at' => $data['paid_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                unset($line['stock_item']);

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    ...$line,
                ]);
            }

            if ($status === 'done') {
                $this->applyStockIn($lines, $request->user(), $purchase->invoice);
            }

            $activeCart = Cart::activeFor($request->user(), 'purchase');
            $activeCart->items()->delete();
            $activeCart->update(['customer_id' => null, 'total' => 0, 'item_count' => 0]);
        });

        return redirect()->route('transaksi.pembelian')->with('success', 'Pembelian berhasil disimpan.');
    }

    /**
     * @param  list<array{stock_item: Model, qty: int}>  $lines
     */
    private function applyStockIn(array $lines, Authenticatable $user, string $reference): void
    {
        foreach ($lines as $line) {
            $stockItem = $line['stock_item'];
            $qty = $line['qty'];

            $stockBefore = $stockItem->stock;
            $stockItem->increment('stock', $qty);

            StockMovementRecorder::record($stockItem, 'in', $qty, $stockBefore, 'PEMBELIAN', $user, $reference);
        }
    }

    public function markDone(Purchase $purchase, Request $request): RedirectResponse
    {
        abort_unless($purchase->supplier_id, 404);

        if ($purchase->status !== 'done') {
            DB::transaction(function () use ($purchase, $request) {
                $purchase->update(['status' => 'done']);

                $this->applyStockIn(
                    $purchase->details->map(fn ($detail) => [
                        'stock_item' => $detail->item,
                        'qty' => (int) $detail->qty,
                    ])->all(),
                    $request->user(),
                    $purchase->invoice,
                );
            });
        }

        return redirect()->route('transaksi.pembelian.show', $purchase)->with('success', 'Pembelian ditandai selesai.');
    }
}
