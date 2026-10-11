<?php

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Laptop;
use App\Models\Reparation;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
    $this->brand = Brand::create(['name' => 'Asus']);
});

test('sales report only includes sales on the selected day', function () {
    $older = Sale::create(['invoice' => 'INV-1', 'subtotal' => 100, 'total' => 100, 'payment_status' => 'lunas']);
    $today = Sale::create(['invoice' => 'INV-2', 'subtotal' => 200, 'total' => 200, 'payment_status' => 'lunas']);
    $older->forceFill(['created_at' => '2026-10-09 10:00:00'])->saveQuietly();
    $today->forceFill(['created_at' => '2026-10-10 10:00:00'])->saveQuietly();

    $response = $this->actingAs($this->user)->get('/laporan/penjualan?from=2026-10-10&to=2026-10-10');

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    expect($props['rows'])->toHaveCount(1)
        ->and($props['rows'][0]['invoice'])->toBe('INV-2')
        ->and($props['summary']['total'])->toBe(200.0);
});

test('servis report counts status groups for the selected day', function () {
    $customer = Customer::create(['name' => 'Andi', 'phone' => '08123', 'is_member' => false]);
    $base = ['customer_id' => $customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati'], 'condition' => [], 'estimated_cost' => 0, 'technician_notes' => '', 'additional_notes' => ''];

    $reparations = [
        ['status' => 'DONE', 'created_at' => '2026-10-10 09:00:00'],
        ['status' => 'RECEIVED', 'created_at' => '2026-10-10 11:00:00'],
        ['status' => 'DONE', 'created_at' => '2026-10-09 11:00:00'],
    ];
    foreach ($reparations as $data) {
        $reparation = Reparation::create([...$base, 'status' => $data['status']]);
        $reparation->forceFill(['created_at' => $data['created_at']])->saveQuietly();
    }

    $response = $this->actingAs($this->user)->get('/laporan/servis?from=2026-10-10&to=2026-10-10');

    $props = $response->original->getData()['page']['props'];
    expect($props['summary'])->toMatchArray(['count' => 2, 'done' => 1, 'pending' => 1]);
});

test('dashboard lists laptops that need a price and laptops ready to post', function () {
    $base = ['brand_id' => $this->brand->id, 'supplier_id' => null, 'warranty' => '1 tahun', 'name' => 'Asus A416', 'sku' => 'LPT-1', 'code' => 'C1', 'stock' => 1, 'buy_price' => 1000, 'status' => 'SIAP JUAL'];
    $needPrice = Laptop::create([...$base, 'sell_price' => null]);
    $needPost = Laptop::create([...$base, 'sku' => 'LPT-2', 'code' => 'C2', 'sell_price' => 5000]);
    Laptop::create([...$base, 'sku' => 'LPT-3', 'code' => 'C3', 'sell_price' => 5000, 'is_posted' => true, 'status' => 'DISPLAY']);

    $response = $this->actingAs($this->user)->get('/dashboard');

    $props = $response->original->getData()['page']['props'];
    expect($props['needPrice'])->toHaveCount(1)
        ->and($props['needPrice'][0]['id'])->toBe($needPrice->id)
        ->and($props['needPost'])->toHaveCount(1)
        ->and($props['needPost'][0]['id'])->toBe($needPost->id);
});

test('marking a laptop as posted sets display status', function () {
    $laptop = Laptop::create(['brand_id' => $this->brand->id, 'supplier_id' => null, 'warranty' => '1 tahun', 'name' => 'Asus', 'sku' => 'LPT-9', 'code' => 'C9', 'stock' => 1, 'buy_price' => 1000, 'sell_price' => 5000, 'status' => 'SIAP JUAL']);

    $this->actingAs($this->user)->patch("/stock/laptop/{$laptop->id}/post")->assertSessionHas('success');

    expect($laptop->fresh())
        ->is_posted->toBeTrue()
        ->status->toBe('DISPLAY');
});
