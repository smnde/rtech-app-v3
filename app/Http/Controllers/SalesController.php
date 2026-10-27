<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\SalesResource;
use App\Models\Cart;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalesDetail;
use App\Models\StockMovement;
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
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $from = $request->filled('from') ? $request->string('from')->toString() : $today;
        $to = $request->filled('to') ? $request->string('to')->toString() : $today;

        // Status hutang/keep menampilkan semua transaksi belum lunas, tanpa batas tanggal.
        $filterByDate = ! in_array($request->string('status')->toString(), ['hutang', 'keep'], true);

        $sales = Sale::query()
            ->with('customer:id,name')
            ->when($request->search, fn ($query, $search) => $query->where('invoice', 'like', "%{$search}%"))
            ->when($request->filled('status'), fn ($query) => $query->where('payment_status', $request->string('status')))
            ->when($filterByDate, fn ($query) => $query
                ->where('created_at', '>=', "{$from} 00:00:00")
                ->where('created_at', '<=', "{$to} 23:59:59"))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Sale $sale) => (new SalesResource($sale))->resolve());

        return inertia('Transaksi/Penjualan', [
            'sales' => $sales,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $cart = Cart::activeFor($request->user(), 'sale')->load(['customer:id,name', 'items.product']);
        $heldCarts = Cart::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'sale')
            ->where('status', 'held')
            ->with(['customer:id,name', 'items.product'])
            ->latest()
            ->get();

        return inertia('Transaksi/PenjualanCreate', [
            'products' => $this->sellableProducts(),
            'cashiers' => User::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'is_member']),
            'cart' => new CartResource($cart),
            'heldCarts' => CartResource::collection($heldCarts),
        ]);
    }

    public function show(Sale $sale): Response
    {
        $sale->load(['user:id,name', 'customer:id,name,phone,is_member', 'details.item']);

        return inertia('Transaksi/PenjualanDetail', [
            'cashiers' => User::query()->orderBy('name')->get(['id', 'name']),
            'sale' => [
                'id' => $sale->id,
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
                    'warranty' => $detail->item?->warranty,
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
     * @return list<array{item_type: string, id: int, label: string, code: ?string, sku: string, sell_price: float, spec: ?string}>
     */
    private function sellableProducts(): array
    {
        $products = [];

        foreach (StockItem::types() as $type => $model) {
            $hasSpecification = in_array($type, ['laptop', 'gadget'], true);
            $hasCode = $type === 'laptop';
            $model::query()
                ->where('stock', '>', 0)
                ->where('sell_price', '>', 0)
                ->when($type === 'laptop', fn ($query) => $query->whereIn('status', ['SIAP JUAL', 'DISPLAY']))
                ->orderBy('name')
                ->get(array_values(array_filter(['id', 'name', 'sku', $hasCode ? 'code' : null, 'sell_price', $hasSpecification ? 'specification' : null])))
                ->each(function ($item) use ($type, $hasSpecification, $hasCode, &$products) {
                    $products[] = [
                        'item_type' => $type,
                        'id' => $item->id,
                        'label' => $item->name,
                        'code' => $hasCode ? $item->code : null,
                        'sku' => $item->sku,
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
        $sale = null;

        DB::transaction(function () use ($data, $request, &$sale) {
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

                $movements[] = [$stockItem, $qty, (bool) ($item['is_bonus'] ?? false)];
            }

            $headerDiscount = (float) ($data['discount'] ?? 0);
            $discount = $lineDiscountTotal + $headerDiscount;
            $total = max(0, $subtotal - $discount);

            $trxAt = filled($data['date'] ?? null)
                ? Carbon::parse($data['date'])->setTimeFrom(now())
                : null;

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
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

            if ($trxAt) {
                // Tanggal transaksi ikut pilihan user; jam tetap jam input.
                $sale->forceFill(['created_at' => $trxAt])->saveQuietly();
            }

            foreach ($lines as $line) {
                SalesDetail::create([
                    'sales_id' => $sale->id,
                    ...$line,
                ]);
            }

            if ($data['payment_status'] !== 'keep') {
                $isDebt = $data['payment_status'] === 'hutang';

                foreach ($movements as [$stockItem, $qty, $isBonus]) {
                    $stockBefore = $stockItem->stock;
                    $stockItem->decrement('stock', $qty);

                    $status = $isBonus ? 'BONUS' : ($isDebt ? 'HUTANG' : 'TERJUAL');
                    StockMovementRecorder::record($stockItem, 'out', $qty, $stockBefore, $status, $request->user(), $sale->invoice, $trxAt?->toDateTimeString());
                }
            }

            $cashInAmount = $data['payment_status'] === 'keep' ? (float) ($data['dp_amount'] ?? 0) : $total;
            if ($cashInAmount > 0) {
                CashFlowRecorder::record(
                    'in',
                    'PENJUALAN',
                    $data['payment_status'] === 'keep'
                        ? "DP {$sale->invoice}"
                        : "Penjualan {$sale->invoice}",
                    $cashInAmount,
                    $sale,
                    $sale->user,
                    $sale->invoice,
                    $trxAt?->toDateString(),
                );
            }

            $activeCart = Cart::activeFor($request->user(), 'sale');
            $activeCart->items()->delete();
            $activeCart->update(['customer_id' => null, 'total' => 0, 'item_count' => 0]);
        });

        if (! $sale instanceof Sale) {
            return back()->with('error', 'Penjualan gagal disimpan.');
        }

        $receiptSale = $sale->load(['user:id,name', 'customer:id,name,phone,is_member']);

        return redirect()
            ->route('transaksi.penjualan.show', [$receiptSale, 'print' => 1])
            ->with('success', 'Penjualan berhasil disimpan.');
    }

    public function markPaid(Sale $sale, Request $request): RedirectResponse
    {
        if (! in_array($sale->payment_status, ['keep', 'hutang'], true)) {
            return back()->with('error', 'Status penjualan tidak perlu dilunasi.');
        }

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $cashier = filled($data['user_id'] ?? null)
            ? User::query()->findOrFail($data['user_id'])
            : $request->user();
        $wasKeep = $sale->payment_status === 'keep';

        // Sisa yang belum tercatat di kas: DP keep sudah masuk, hutang sudah tercatat penuh saat simpan.
        $alreadyRecorded = (float) CashFlow::query()
            ->where('reference', $sale->invoice)
            ->where('type', 'in')
            ->sum('amount');
        $outstanding = max(0, (float) $sale->total - $alreadyRecorded);

        DB::transaction(function () use ($sale, $cashier, $wasKeep, $outstanding) {
            // Kasir pelunasan menggantikan kasir saat transaksi/DP dibuat.
            $sale->update([
                'payment_status' => 'lunas',
                'paid_at' => now(),
                'user_id' => $cashier->id,
            ]);

            // keep = pelunasan custom → jalankan mutasi keluar saat dilunasi.
            if ($wasKeep) {
                $this->recordOut(
                    $sale->details->map(fn ($detail) => [
                        'stock_item' => $detail->item,
                        'qty' => (int) $detail->qty,
                        'is_bonus' => (float) $detail->price <= 0,
                    ])->all(),
                    'TERJUAL',
                    $cashier,
                    $sale->invoice,
                );
            }

            CashFlowRecorder::record(
                'in',
                CashFlowRecorder::SALE_CATEGORY,
                "Pelunasan {$sale->invoice}",
                $outstanding,
                $sale,
                $cashier,
                $sale->invoice,
            );
        });

        return redirect()->route('transaksi.penjualan.show', $sale)->with('success', 'Penjualan sudah dilunasi.');
    }

    public function destroy(Sale $sale, Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('delete sale'), 403);

        DB::transaction(function () use ($sale) {
            if (StockMovement::query()->where('reference', $sale->invoice)->exists()) {
                $this->reverseStockOut($sale);
            }

            CashFlowRecorder::deleteByReference($sale->invoice);

            $sale->details()->delete();
            $sale->delete();
        });

        return redirect()->route('transaksi.penjualan')->with('success', 'Penjualan dihapus, stok dikembalikan.');
    }

    private function reverseStockOut(Sale $sale): void
    {
        foreach ($sale->details as $detail) {
            $stockItem = StockItem::modelFor($detail->item_type)::query()
                ->lockForUpdate()
                ->find($detail->item_id);

            if (! $stockItem) {
                continue;
            }

            $stockItem->increment('stock', $detail->qty);
            StockMovementRecorder::syncStatus($stockItem);
        }

        StockMovement::query()->where('reference', $sale->invoice)->delete();
    }

    /**
     * @param  list<array{stock_item: Model, qty: int}>  $lines
     */
    /**
     * @param  list<array{stock_item: Model, qty: int, is_bonus?: bool}>  $lines
     */
    private function recordOut(array $lines, string $defaultStatus, Authenticatable $user, string $reference): void
    {
        foreach ($lines as $line) {
            $stockItem = $line['stock_item'];
            $qty = $line['qty'];

            $stockBefore = $stockItem->stock;
            $stockItem->decrement('stock', $qty);

            $status = ($line['is_bonus'] ?? false) ? 'BONUS' : $defaultStatus;
            StockMovementRecorder::record($stockItem, 'out', $qty, $stockBefore, $status, $user, $reference);
        }
    }
}
