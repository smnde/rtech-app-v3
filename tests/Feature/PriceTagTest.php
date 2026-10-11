<?php

use App\Models\Brand;
use App\Models\Laptop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
});

test('price tag page lists only items with a sell price and formats the specification', function () {
    $brand = Brand::create(['name' => 'Asus']);

    Laptop::create([
        'brand_id' => $brand->id,
        'name' => 'ROG Strix G16',
        'sku' => 'LTP-0001',
        'warranty' => '1 tahun',
        'stock' => 2,
        'status' => 'SIAP JUAL',
        'buy_price' => 10000000,
        'sell_price' => 12000000,
        'specification' => ['cpu' => 'Intel i7', 'memory' => '16GB'],
    ]);

    Laptop::create([
        'brand_id' => $brand->id,
        'name' => 'Tanpa Harga',
        'sku' => 'LTP-0002',
        'warranty' => '1 tahun',
        'stock' => 2,
        'status' => 'SIAP JUAL',
        'buy_price' => 10000000,
        'sell_price' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('price-tag'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PriceTag/Index')
            ->has('items', 1)
            ->where('items.0.code', 'LTP-0001')
            ->where('items.0.sell_price', 12000000)
            ->where('items.0.specification', "Cpu: Intel i7\nMemory: 16GB")
        );
});
