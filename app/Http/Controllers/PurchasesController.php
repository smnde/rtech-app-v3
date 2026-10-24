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
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CashFlowRecorder;
use App\Support\StockItem;
use App\Support\StockMovementRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class PurchasesController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $from = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to = $request->filled('to') ? $request->string('to')->toString() : $today;

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'customer:id,name'])
            ->when($request->search, fn ($query, $search) => $query->where('invoice', 'like', "%{$search}%"))
            ->when($request->filled('status'), fn ($query) => $query->where('payment_status', $request->string('status')))
            ->when($request->filled('order_status'), fn ($query) => $query->where('status', $request->string('order_status')))
            ->where('created_at', '>=', "{$from} 00:00:00")
            ->where('created_at', '<=', "{$to} 23:59:59")
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Transaksi/Pembelian', [
            'purchases' => PurchasesResource::collection($purchases),
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'order_status' => $request->order_status,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $cart = Cart::activeFor($request->user(), 'purchase')->load(['customer:id,name', 'items.product']);
        $heldCarts = Cart::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'purchase')
            ->where('status', 'held')
            ->with(['customer:id,name', 'items.product'])
            ->latest()
            ->get();

        return inertia('Transaksi/PembelianCreate', [
            'products' => $this->purchasableProducts(),
            'cashiers' => User::query()->orderBy('name')->get(['id', 'name']),
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
                'id' => $purchase->id,
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
                    'warranty' => $detail->item?->warranty,
                ])->values(),
            ],
        ]);
    }

    /**
     * @return list<array{item_type: string, id: int, label: string, code: ?string, sku: string, buy_price: float}>
     */
    private function purchasableProducts(): array
    {
        $products = [];

        foreach (StockItem::types() as $type => $model) {
            $hasCode = $type === 'laptop';
            $model::query()
                ->orderBy('name')
                ->get(array_values(array_filter(['id', 'name', 'sku', $hasCode ? 'code' : null, 'buy_price'])))
                ->each(function ($item) use ($type, $hasCode, &$products) {
                    $products[] = [
                        'item_type' => $type,
                        'id' => $item->id,
                        'label' => $item->name,
                        'code' => $hasCode ? $item->code : null,
                        'sku' => $item->sku,
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

            $trxAt = filled($data['date'] ?? null)
                ? Carbon::parse($data['date'])->setTimeFrom(now())
                : null;

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'buy_from_reference' => $data['buy_from_reference'] ?? null,
                'total' => $total,
                'payment_status' => $data['payment_status'],
                'status' => $status,
                'paid_at' => $data['paid_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($trxAt) {
                // Tanggal transaksi ikut pilihan user; jam tetap jam input.
                $purchase->forceFill(['created_at' => $trxAt])->saveQuietly();
            }

            foreach ($lines as $line) {
                unset($line['stock_item']);

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    ...$line,
                ]);
            }

            if ($status === 'done') {
                $this->applyStockIn($lines, $request->user(), $purchase->invoice, $trxAt?->toDateTimeString());
            }

            if ($data['payment_status'] === 'lunas' && $total > 0) {
                CashFlowRecorder::record(
                    'out',
                    'PEMBELIAN',
                    "Pembelian {$purchase->invoice}",
                    $total,
                    $purchase,
                    $request->user(),
                    $purchase->invoice,
                    $trxAt?->toDateString(),
                );
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
    private function applyStockIn(array $lines, Authenticatable $user, string $reference, ?string $at = null): void
    {
        foreach ($lines as $line) {
            $stockItem = $line['stock_item'];
            $qty = $line['qty'];

            $stockBefore = $stockItem->stock;
            $stockItem->increment('stock', $qty);

            StockMovementRecorder::record($stockItem, 'in', $qty, $stockBefore, 'PEMBELIAN', $user, $reference, $at);
        }
    }

    public function markDone(Purchase $purchase, Request $request): RedirectResponse
    {
        abort_unless($purchase->supplier_id, 404);

        if ($purchase->status !== 'done') {
            DB::transaction(function () use ($purchase, $request) {
                $purchase->update(['status' => 'done']);

                $purchase->load('details');
                $lines = $purchase->details->map(fn ($detail) => [
                    'stock_item' => $detail->item,
                    'qty' => (int) $detail->qty,
                ])->all();

                $this->applyStockIn($lines, $request->user(), $purchase->invoice);
            });
        }

        return redirect()->route('transaksi.pembelian.show', $purchase)->with('success', 'Pembelian ditandai selesai.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($purchase) {
            if (StockMovement::query()->where('reference', $purchase->invoice)->exists()) {
                $this->reverseStockIn($purchase);
            }

            CashFlowRecorder::deleteByReference($purchase->invoice);

            $purchase->details()->delete();
            $purchase->delete();
        });

        return redirect()->route('transaksi.pembelian')->with('success', 'Pembelian dihapus, stok dikembalikan.');
    }

    private function reverseStockIn(Purchase $purchase): void
    {
        foreach ($purchase->details as $detail) {
            $stockItem = StockItem::modelFor($detail->item_type)::query()
                ->lockForUpdate()
                ->find($detail->item_id);

            if (! $stockItem) {
                continue;
            }

            $stockItem->decrement('stock', $detail->qty);
            StockMovementRecorder::syncStatus($stockItem);
        }

        StockMovement::query()->where('reference', $purchase->invoice)->delete();
    }
}
