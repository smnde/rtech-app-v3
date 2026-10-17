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
use App\Support\StockMovementRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
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

    public function show(Sale $sale): Response
    {
        $sale->load(['user:id,name', 'customer:id,name,phone,is_member', 'details.item']);

        return inertia('Transaksi/PenjualanDetail', [
            'sale' => [
                'invoice' => $sale->invoice,
                'date' => $sale->created_at->toDateTimeString(),
                'cashier' => $sale->user?->name,
                'customer' => $sale->customer ? [
                    'id' => $sale->customer->id,
                    'name' => $sale->customer->name,
                    'phone' => $sale->customer->phone,
                    'is_member' => $sale->customer->is_member,
                ] : null,
                'payment_status' => $sale->payment_status,
                'payment_method' => $sale->payment_method,
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'header_discount' => (float) $sale->header_discount,
                'total' => (float) $sale->total,
                'dp_amount' => $sale->dp_amount !== null ? (float) $sale->dp_amount : null,
                'paid_at' => $sale->paid_at?->toDateTimeString(),
                'notes' => $sale->notes,
                'items' => $sale->details->map(fn ($detail) => [
                    'item_name' => $detail->item?->name,
                    'item_code' => $detail->item?->sku,
                    'qty' => $detail->qty,
                    'price' => (float) $detail->price,
                    'discount' => max(0, ($detail->qty * (float) $detail->price) - (float) $detail->subtotal),
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
            $lineDiscountTotal = 0;
            $lines = [];
            $movements = [];

            foreach ($data['items'] as $item) {
                $stockItem = StockItem::modelFor($item['item_type'])::query()
                    ->lockForUpdate()
                    ->findOrFail($item['item_id']);

                $qty = (int) $item['qty'];
                $price = (float) $item['price'];
                $lineDiscount = min((float) ($item['discount'] ?? 0), $qty * $price);
                $lineSubtotal = ($qty * $price) - $lineDiscount;

                if ($stockItem->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$stockItem->name} tidak cukup (sisa {$stockItem->stock}).",
                    ]);
                }

                $subtotal += $qty * $price;
                $lineDiscountTotal += $lineDiscount;
                $lines[] = [
                    'item_type' => $item['item_type'],
                    'item_id' => $stockItem->id,
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                ];

                $movements[] = [$stockItem, $qty];
            }

            $headerDiscount = (float) ($data['discount'] ?? 0);
            $discount = $lineDiscountTotal + $headerDiscount;
            $total = max(0, $subtotal - $discount);

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'header_discount' => $headerDiscount,
                'total' => $total,
                'payment_status' => $data['payment_status'],
                'payment_method' => $data['payment_method'],
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

            if ($data['payment_status'] !== 'keep') {
                foreach ($movements as [$stockItem, $qty]) {
                    $stockBefore = $stockItem->stock;
                    $stockItem->decrement('stock', $qty);

                    StockMovementRecorder::record($stockItem, 'out', $qty, $stockBefore, 'TERJUAL', $request->user(), $sale->invoice);
                }
            }

            $activeCart = Cart::activeFor($request->user(), 'sale');
            $activeCart->items()->delete();
            $activeCart->update(['customer_id' => null, 'total' => 0, 'item_count' => 0]);
        });

        return redirect()->route('transaksi.penjualan')->with('success', 'Penjualan berhasil disimpan.');
    }

    public function markPaid(Sale $sale, Request $request): RedirectResponse
    {
        if (! in_array($sale->payment_status, ['keep', 'hutang'], true)) {
            return back()->with('error', 'Status penjualan tidak perlu dilunasi.');
        }

        $wasKeep = $sale->payment_status === 'keep';

        DB::transaction(function () use ($sale, $request, $wasKeep) {
            $sale->update(['payment_status' => 'lunas', 'paid_at' => now()]);

            // keep = pelunasan custom → jalankan mutasi keluar saat dilunasi.
            if ($wasKeep) {
                $this->recordOut(
                    $sale->details->map(fn ($detail) => [
                        'stock_item' => $detail->item,
                        'qty' => (int) $detail->qty,
                    ])->all(),
                    $request->user(),
                    $sale->invoice,
                );
            }
        });

        return redirect()->route('transaksi.penjualan.show', $sale)->with('success', 'Penjualan sudah dilunasi.');
    }

    /**
     * @param  list<array{stock_item: Model, qty: int}>  $lines
     */
    private function recordOut(array $lines, Authenticatable $user, string $reference): void
    {
        foreach ($lines as $line) {
            $stockItem = $line['stock_item'];
            $qty = $line['qty'];

            $stockBefore = $stockItem->stock;
            $stockItem->decrement('stock', $qty);

            StockMovementRecorder::record($stockItem, 'out', $qty, $stockBefore, 'TERJUAL', $user, $reference);
        }
    }
}
