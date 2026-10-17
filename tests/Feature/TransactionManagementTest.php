<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\Laptop;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SalesDetail;
use App\Models\Sparepart;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
    $this->laptop = Laptop::create([
        'brand_id' => Brand::create(['name' => 'Asus'])->id,
        'name' => 'ROG Strix G16',
        'warranty' => '1 tahun',
        'stock' => 3,
        'status' => 'SIAP JUAL',
        'buy_price' => 10000000,
        'sell_price' => 12000000,
    ]);
});

test('keep sale does not move stock at store, moves only when lunas', function () {
    $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '0812', 'is_member' => false]);

    $this->actingAs($this->user)->post('/transaksi/penjualan', [
        'customer_id' => $customer->id,
        'payment_status' => 'keep',
        'payment_method' => 'cash',
        'dp_amount' => 2000000,
        'paid_at' => now()->toDateString(),
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 12000000],
        ],
    ])->assertSessionHas('success');

    // DP keep → stok belum berubah, belum ada mutasi.
    expect($this->laptop->fresh()->stock)->toBe(3)
        ->and(StockMovement::count())->toBe(0);

    $sale = Sale::with('details')->firstOrFail();
    expect($sale->payment_status)->toBe('keep')
        ->and((float) $sale->total)->toBe(12000000.0)
        ->and($sale->invoice)->toStartWith('INV-')
        ->and($sale->details)->toHaveCount(1)
        ->and($sale->details->first()->item_type)->toBe('laptop');

    // Lunas → mutasi keluar tercatat, stok berkurang.
    $this->actingAs($this->user)->post(route('transaksi.penjualan.lunas', $sale))
        ->assertRedirect(route('transaksi.penjualan.show', $sale));

    expect($sale->fresh()->payment_status)->toBe('lunas')
        ->and($this->laptop->fresh()->stock)->toBe(2);

    $movement = StockMovement::firstOrFail();
    expect($movement->type)->toBe('out')
        ->and($movement->quantity)->toBe(1)
        ->and($movement->reference)->toBe($sale->invoice);

    $cashFlow = CashFlow::firstOrFail();
    expect($cashFlow->type)->toBe('in')
        ->and((float) $cashFlow->amount)->toBe(2000000.0)
        ->and($cashFlow->reference)->toBe($sale->invoice);
});

test('hutang sale moves stock at store and lunas does not move twice', function () {
    $this->actingAs($this->user)->post('/transaksi/penjualan', [
        'payment_status' => 'hutang',
        'payment_method' => 'tf',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 12000000],
        ],
    ])->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(2)
        ->and(StockMovement::count())->toBe(1);

    $sale = Sale::firstOrFail();

    $this->actingAs($this->user)->post(route('transaksi.penjualan.lunas', $sale))
        ->assertRedirect(route('transaksi.penjualan.show', $sale));

    expect($sale->fresh()->payment_status)->toBe('lunas')
        ->and($this->laptop->fresh()->stock)->toBe(2)
        ->and(StockMovement::count())->toBe(1);

    $cashFlow = CashFlow::firstOrFail();
    expect($cashFlow->type)->toBe('in')
        ->and((float) $cashFlow->amount)->toBe(12000000.0)
        ->and($cashFlow->reference)->toBe($sale->invoice);
});

test('sale store rolls back everything when one item has insufficient stock', function () {
    $sparepart = Sparepart::create([
        'brand_id' => Brand::create(['name' => 'Generic'])->id,
        'category_id' => Category::create(['name' => 'Cooling'])->id,
        'supplier_id' => Supplier::create(['name' => 'PT Sumber', 'phone' => '0215', 'address' => 'Jakarta'])->id,
        'warranty' => '1 bulan',
        'notes' => '',
        'name' => 'Fan CPU',
        'stock' => 1,
        'status' => 'READY STOCK',
        'buy_price' => 50000,
        'sell_price' => 80000,
    ]);

    $this->actingAs($this->user)->post('/transaksi/penjualan', [
        'payment_status' => 'lunas',
        'payment_method' => 'cash',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 12000000],
            ['item_type' => 'sparepart', 'item_id' => $sparepart->id, 'qty' => 5, 'price' => 80000],
        ],
    ])->assertSessionHasErrors('items');

    expect($this->laptop->fresh()->stock)->toBe(3)
        ->and($sparepart->fresh()->stock)->toBe(1)
        ->and(Sale::count())->toBe(0);
});

test('keep sale requires dp amount and payoff date', function () {
    $this->actingAs($this->user)->post('/transaksi/penjualan', [
        'payment_status' => 'keep',
        'payment_method' => 'cash',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 12000000],
        ],
    ])->assertSessionHasErrors(['dp_amount', 'paid_at']);
});

test('supplier purchase ordered does not move stock until done', function () {
    $supplier = Supplier::create(['name' => 'PT Sumber', 'phone' => '0215', 'address' => 'Jakarta']);

    $this->actingAs($this->user)->post('/transaksi/pembelian', [
        'supplier_id' => $supplier->id,
        'payment_status' => 'hutang',
        'status' => 'ordered',
        'buy_from_reference' => 'NOTA-123',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 2, 'price' => 10000000],
        ],
    ])->assertRedirect(route('transaksi.pembelian'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(3)
        ->and(StockMovement::count())->toBe(0);

    $purchase = Purchase::with('details')->firstOrFail();
    expect($purchase->status)->toBe('ordered')
        ->and($purchase->payment_status)->toBe('hutang')
        ->and((float) $purchase->total)->toBe(20000000.0)
        ->and($purchase->invoice)->toStartWith('PRC-')
        ->and($purchase->details)->toHaveCount(1);

    $this->actingAs($this->user)->post(route('transaksi.pembelian.done', $purchase))
        ->assertRedirect(route('transaksi.pembelian.show', $purchase));

    expect($purchase->fresh()->status)->toBe('done')
        ->and($this->laptop->fresh()->stock)->toBe(5);

    $movement = StockMovement::firstOrFail();
    expect($movement->type)->toBe('in')
        ->and($movement->quantity)->toBe(2)
        ->and($movement->reference)->toBe($purchase->invoice);
});

test('non-supplier purchase forces done status and moves stock at store time', function () {
    $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '0812', 'is_member' => false]);

    $this->actingAs($this->user)->post('/transaksi/pembelian', [
        'customer_id' => $customer->id,
        'payment_status' => 'lunas',
        'status' => 'pending',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 10000000],
        ],
    ])->assertRedirect(route('transaksi.pembelian'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(4);

    $purchase = Purchase::firstOrFail();
    expect($purchase->status)->toBe('done')
        ->and(StockMovement::count())->toBe(1)
        ->and(StockMovement::first()->type)->toBe('in');
});

test('sale index and create pages render for users with permission', function () {
    $this->actingAs($this->user)->get('/transaksi/penjualan')->assertOk();
    $this->actingAs($this->user)->get('/transaksi/penjualan/create')->assertOk();
    $this->actingAs($this->user)->get('/transaksi/pembelian')->assertOk();
    $this->actingAs($this->user)->get('/transaksi/pembelian/create')->assertOk();
});

test('destroying a sale restores stock and deletes related movements', function () {
    $sale = Sale::create([
        'user_id' => $this->user->id,
        'subtotal' => 12000000,
        'discount' => 0,
        'header_discount' => 0,
        'total' => 12000000,
        'payment_status' => 'hutang',
        'payment_method' => 'tf',
        'notes' => null,
    ]);

    SalesDetail::create([
        'sales_id' => $sale->id,
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'qty' => 1,
        'price' => 12000000,
        'subtotal' => 12000000,
    ]);

    StockMovement::create([
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'user_id' => $this->user->id,
        'type' => 'out',
        'status' => 'TERJUAL',
        'movement_date' => now()->toDateString(),
        'quantity' => 1,
        'stock_before' => 3,
        'stock_after' => 2,
        'reference' => $sale->invoice,
    ]);

    $this->laptop->decrement('stock', 1);
    expect($this->laptop->fresh()->stock)->toBe(2);

    CashFlow::create([
        'user_id' => $this->user->id,
        'item_type' => Sale::class,
        'item_id' => $sale->id,
        'type' => 'in',
        'category' => 'PENJUALAN',
        'description' => "Penjualan {$sale->invoice}",
        'amount' => 12000000,
        'flow_date' => now()->toDateString(),
        'reference' => $sale->invoice,
    ]);

    $this->actingAs($this->user)->delete(route('transaksi.penjualan.destroy', $sale))
        ->assertRedirect(route('transaksi.penjualan'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(3)
        ->and(StockMovement::count())->toBe(0)
        ->and(Sale::count())->toBe(0)
        ->and(DB::table('sales_details')->count())->toBe(0)
        ->and(CashFlow::count())->toBe(0);
});

test('destroying a sale without movements leaves stock untouched', function () {
    $sale = Sale::create([
        'user_id' => $this->user->id,
        'subtotal' => 12000000,
        'discount' => 0,
        'header_discount' => 0,
        'total' => 12000000,
        'payment_status' => 'keep',
        'payment_method' => 'cash',
        'dp_amount' => 2000000,
        'paid_at' => now()->toDateString(),
        'notes' => null,
    ]);

    SalesDetail::create([
        'sales_id' => $sale->id,
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'qty' => 1,
        'price' => 12000000,
        'subtotal' => 12000000,
    ]);

    $this->actingAs($this->user)->delete(route('transaksi.penjualan.destroy', $sale))
        ->assertRedirect(route('transaksi.penjualan'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(3)
        ->and(StockMovement::count())->toBe(0)
        ->and(Sale::count())->toBe(0);
});

test('destroying a done purchase reverses stock in and deletes related movements', function () {
    $supplier = Supplier::create(['name' => 'PT Sumber', 'phone' => '0215', 'address' => 'Jakarta']);

    $purchase = Purchase::create([
        'supplier_id' => $supplier->id,
        'user_id' => $this->user->id,
        'total' => 20000000,
        'payment_status' => 'lunas',
        'status' => 'done',
        'notes' => null,
    ]);

    PurchaseDetail::create([
        'purchase_id' => $purchase->id,
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'qty' => 2,
        'buy_price' => 10000000,
        'subtotal' => 20000000,
    ]);

    StockMovement::create([
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'user_id' => $this->user->id,
        'type' => 'in',
        'status' => 'PEMBELIAN',
        'movement_date' => now()->toDateString(),
        'quantity' => 2,
        'stock_before' => 3,
        'stock_after' => 5,
        'reference' => $purchase->invoice,
    ]);

    $this->laptop->increment('stock', 2);
    expect($this->laptop->fresh()->stock)->toBe(5);

    CashFlow::create([
        'user_id' => $this->user->id,
        'item_type' => Purchase::class,
        'item_id' => $purchase->id,
        'type' => 'out',
        'category' => 'PEMBELIAN',
        'description' => "Pembelian {$purchase->invoice}",
        'amount' => 20000000,
        'flow_date' => now()->toDateString(),
        'reference' => $purchase->invoice,
    ]);

    $this->actingAs($this->user)->delete(route('transaksi.pembelian.destroy', $purchase))->assertRedirect(route('transaksi.pembelian'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(3)
        ->and(StockMovement::count())->toBe(0)
        ->and(Purchase::count())->toBe(0)
        ->and(DB::table('purchase_details')->count())->toBe(0)
        ->and(CashFlow::count())->toBe(0);
});

test('sale create only lists sellable products with stock, price and allowed laptop status', function () {
    $brand = Brand::firstOrFail();
    $make = fn (array $attributes) => Laptop::create([
        'brand_id' => $brand->id,
        'warranty' => '1 tahun',
        'stock' => 1,
        'status' => 'SIAP JUAL',
        'sell_price' => 5000000,
        ...$attributes,
    ]);
    $make(['name' => 'Siap Jual']);
    $make(['name' => 'Display', 'status' => 'DISPLAY']);
    $make(['name' => 'Kendala', 'status' => 'KENDALA']);
    $make(['name' => 'Stok Nol', 'stock' => 0]);
    $make(['name' => 'Harga Nol', 'sell_price' => 0]);
    $make(['name' => 'Harga Null', 'sell_price' => null]);

    $products = $this->actingAs($this->user)->get('/transaksi/penjualan/create')
        ->original->getData()['page']['props']['products'];

    expect(collect($products)->pluck('label')->sort()->values()->all())
        ->toBe(['Display', 'ROG Strix G16', 'Siap Jual']);

    $this->laptop->update(['specification' => ['cpu' => 'Ryzen 7', 'gpu' => 'RTX 4060', 'memory' => '16GB', 'storage' => '512GB SSD']]);
    $products = $this->actingAs($this->user)->get('/transaksi/penjualan/create')
        ->original->getData()['page']['props']['products'];

    expect(collect($products)->firstWhere('label', 'ROG Strix G16')['spec'])->toBe('Ryzen 7 · 16GB · 512GB SSD');
});
