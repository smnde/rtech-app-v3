<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Laptop;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Sparepart;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

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

test('sale store decrements stock and records the sale with keep payment', function () {
    $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '0812', 'is_member' => false]);

    $this->actingAs($this->user)->post('/transaksi/penjualan', [
        'customer_id' => $customer->id,
        'payment_status' => 'keep',
        'dp_amount' => 2000000,
        'paid_at' => '2026-11-01',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 12000000],
        ],
    ])->assertRedirect(route('transaksi.penjualan'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(2);

    $sale = Sale::with('details')->firstOrFail();
    expect($sale->payment_status)->toBe('keep')
        ->and((float) $sale->dp_amount)->toBe(2000000.0)
        ->and((float) $sale->total)->toBe(12000000.0)
        ->and($sale->invoice)->toStartWith('INV-')
        ->and($sale->details)->toHaveCount(1)
        ->and($sale->details->first()->item_type)->toBe('laptop');
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
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 1, 'price' => 12000000],
        ],
    ])->assertSessionHasErrors(['dp_amount', 'paid_at']);
});

test('purchase store increments stock and records po type with hutang status', function () {
    $this->actingAs($this->user)->post('/transaksi/pembelian', [
        'payment_status' => 'hutang',
        'po_type' => 'po',
        'buy_from_reference' => 'NOTA-123',
        'items' => [
            ['item_type' => 'laptop', 'item_id' => $this->laptop->id, 'qty' => 2, 'price' => 10000000],
        ],
    ])->assertRedirect(route('transaksi.pembelian'))->assertSessionHas('success');

    expect($this->laptop->fresh()->stock)->toBe(5);

    $purchase = Purchase::with('details')->firstOrFail();
    expect($purchase->payment_status)->toBe('hutang')
        ->and($purchase->po_type)->toBe('po')
        ->and((float) $purchase->total)->toBe(20000000.0)
        ->and($purchase->invoice)->toStartWith('PRC-')
        ->and($purchase->details)->toHaveCount(1);
});

test('sale index and create pages render for users with permission', function () {
    $this->actingAs($this->user)->get('/transaksi/penjualan')->assertOk();
    $this->actingAs($this->user)->get('/transaksi/penjualan/create')->assertOk();
    $this->actingAs($this->user)->get('/transaksi/pembelian')->assertOk();
    $this->actingAs($this->user)->get('/transaksi/pembelian/create')->assertOk();
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
