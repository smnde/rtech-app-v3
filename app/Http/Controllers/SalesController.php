<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\SalesResource;
use App\Models\Cart;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Promo;
use App\Models\Sale;
use App\Models\SalesDetail;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\CashFlowRecorder;
use App\Support\LikePattern;
use App\Support\StockItem;
use App\Support\StockMovementRecorder;
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
            ->when($request->search, fn ($query, $search) => $query->where('invoice', 'like', LikePattern::contains($search)))
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
            // Promo yang boleh dipakai kasir hari ini.
            'promos' => Promo::query()
                ->active()
                ->orderBy('title')
                ->get(['id', 'title', 'type', 'value', 'item_types', 'starts_at', 'ends_at'])
                ->map(fn (Promo $promo) => [
                    'id' => $promo->id,
                    'title' => $promo->title,
                    'type' => $promo->type,
                    'value' => (float) $promo->value,
                    'item_types' => $promo->item_types ?? [],
                    'ends_at' => $promo->ends_at?->toDateString(),
                ]),
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
                'promo_title' => $sale->promo_title,
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
                    'promo_discount' => (float) $detail->promo_discount,
                    'subtotal' => (float) $detail->subtotal - (float) $detail->promo_discount,
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
            $promoTotal = 0;
            $lines = [];
            $movements = [];

            // Promo dipilih user, tapi yang menentukan besarannya tetap server:
            // promo harus benar-benar sedang berlaku hari ini.
            $promo = null;
            if (filled($data['promo_id'] ?? null)) {
                $promo = Promo::query()->active()->find($data['promo_id']);

                if (! $promo) {
                    throw ValidationException::withMessages([
                        'promo_id' => 'Promo yang dipilih sudah tidak berlaku.',
                    ]);
                }
            }

            foreach ($data['items'] as $item) {
                $stockItem = StockItem::modelFor($item['item_type'])::query()
                    ->lockForUpdate()
                    ->findOrFail($item['item_id']);

                $qty = (int) $item['qty'];
                $isBonus = (bool) ($item['is_bonus'] ?? false);

                // Harga selalu dari database; negosiasi dilakukan lewat diskon item.
                // Bonus bukan diskon: harganya memang 0, jadi tidak muncul sebagai potongan di nota.
                $price = $isBonus ? 0.0 : (float) $stockItem->sell_price;
                $gross = $qty * $price;

                $lineDiscount = $isBonus ? 0.0 : (float) ($item['discount'] ?? 0);

                if ($lineDiscount > $gross) {
                    throw ValidationException::withMessages([
                        'items' => "Diskon {$stockItem->name} (Rp {$lineDiscount}) melebihi harganya (Rp {$gross}).",
                    ]);
                }

                // Potongan promo dihitung per unit (harga asli × persen, atau nominal),
                // jadi hasilnya tetap bulat walau qty lebih dari satu.
                $promoUnit = $promo && ! $isBonus && in_array($item['item_type'], $promo->item_types ?? [], true)
                    ? $promo->discountForUnit($price)
                    : 0.0;
                $linePromo = $promoUnit * $qty;

                if ($linePromo + $lineDiscount > $gross) {
                    throw ValidationException::withMessages([
                        'items' => "Total potongan {$stockItem->name} (Rp ".($linePromo + $lineDiscount).") melebihi harganya (Rp {$gross}).",
                    ]);
                }

                $lineSubtotal = $gross - $lineDiscount;

                if ($stockItem->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$stockItem->name} tidak cukup (sisa {$stockItem->stock}).",
                    ]);
                }

                $subtotal += $gross;
                $lineDiscountTotal += $lineDiscount;
                $promoTotal += $linePromo;
                $lines[] = [
                    'item_type' => $item['item_type'],
                    'item_id' => $stockItem->id,
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $lineSubtotal,
                    'promo_discount' => $linePromo,
                ];

                $movements[] = [$stockItem, $qty, $isBonus];
            }

            $discount = $lineDiscountTotal + $promoTotal;
            $total = $subtotal - $discount;

            if ($total < 0) {
                throw ValidationException::withMessages([
                    'items' => 'Total potongan melebihi harga barang. Periksa diskon item dan promo.',
                ]);
            }

            $trxAt = filled($data['date'] ?? null)
                ? Carbon::parse($data['date'])->setTimeFrom(now())
                : null;

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'promo_id' => $promo?->id,
                // Judul di-snapshot supaya nota lama tetap terbaca walau promo diubah/dihapus.
                'promo_title' => $promo?->title,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'header_discount' => 0,
                'total' => $total,
                'payment_status' => $data['payment_status'],
                'payment_method' => $data['payment_method'],
                'dp_amount' => $data['payment_status'] === 'keep' ? $data['dp_amount'] : null,
                // keep & hutang: paid_at menyimpan tanggal tempo pelunasan sampai transaksi dilunasi.
                'paid_at' => in_array($data['payment_status'], ['keep', 'hutang'], true) ? ($data['paid_at'] ?? null) : null,
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

            // Stok langsung berkurang untuk semua status (lunas, keep, hutang).
            $isDebt = $data['payment_status'] === 'hutang';

            foreach ($movements as [$stockItem, $qty, $isBonus]) {
                $stockBefore = $stockItem->stock;
                $stockItem->decrement('stock', $qty);

                $status = $isBonus ? 'BONUS' : ($isDebt ? 'HUTANG' : 'TERJUAL');
                StockMovementRecorder::record($stockItem, 'out', $qty, $stockBefore, $status, $sale->user, $sale->invoice, $trxAt?->toDateTimeString());
            }

            $cashInAmount = $data['payment_status'] === 'keep' ? (float) ($data['dp_amount'] ?? 0) : $total;
            if ($cashInAmount > 0) {
                $isKeep = $data['payment_status'] === 'keep';

                if ($isKeep) {
                    // DP bukan per item, tetap satu baris.
                    CashFlowRecorder::record(
                        'in',
                        'PENJUALAN',
                        "DP {$sale->invoice}",
                        $cashInAmount,
                        $sale,
                        $sale->user,
                        $sale->invoice,
                        $trxAt?->toDateString(),
                    );
                } else {
                    // Rinci per item; baris dengan subtotal 0 dilewati.
                    $sale->load('details.item');
                    foreach ($sale->details as $detail) {
                        $lineSubtotal = (float) $detail->subtotal - (float) $detail->promo_discount;
                        if ($lineSubtotal <= 0) {
                            continue;
                        }

                        $label = $detail->item?->name ?? 'Item';
                        $qty = (int) $detail->qty;

                        CashFlowRecorder::record(
                            'in',
                            'PENJUALAN',
                            "Penjualan {$sale->invoice} — {$label}".($qty > 1 ? " x{$qty}" : ''),
                            $lineSubtotal,
                            $sale,
                            $sale->user,
                            $sale->invoice,
                            $trxAt?->toDateString(),
                        );
                    }
                }
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
        // Sisa yang belum tercatat di kas: DP keep sudah masuk, hutang sudah tercatat penuh saat simpan.
        $alreadyRecorded = (float) CashFlow::query()
            ->where('reference', $sale->invoice)
            ->where('type', 'in')
            ->sum('amount');
        $outstanding = max(0, (float) $sale->total - $alreadyRecorded);

        DB::transaction(function () use ($sale, $cashier, $outstanding) {
            // Kasir pelunasan menggantikan kasir saat transaksi/DP dibuat.
            $sale->update([
                'payment_status' => 'lunas',
                'paid_at' => now(),
                'user_id' => $cashier->id,
            ]);

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
}
