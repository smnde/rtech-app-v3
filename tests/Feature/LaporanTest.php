<?php

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Laptop;
use App\Models\Reparation;
use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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

    expect($props['summary']['count'])->toBe(1)
        ->and($props['summary']['total'])->toBe(200.0);
});

test('servis report lists taken reparations with their confirmed total', function () {
    $customer = Customer::create(['name' => 'Andi', 'phone' => '08123', 'is_member' => false]);
    $base = ['customer_id' => $customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati'], 'condition' => [], 'technician_notes' => '', 'additional_notes' => ''];

    $taken = Reparation::create([...$base, 'status' => 'TAKEN', 'payment_status' => 'unpaid', 'total_price' => 250000]);
    $taken->forceFill(['created_at' => '2026-10-10 09:00:00'])->saveQuietly();
    $stage = $taken->stages()->create(['sequence' => 1, 'status' => 'CONFIRMED']);
    $stage->items()->create(['label' => 'Ganti LCD', 'cost' => 250000]);

    $done = Reparation::create([...$base, 'status' => 'DONE', 'payment_status' => 'paid', 'total_price' => 100000]);
    $done->forceFill(['created_at' => '2026-10-10 11:00:00'])->saveQuietly();

    $response = $this->actingAs($this->user)->get('/laporan/servis?from=2026-10-10&to=2026-10-10');

    $props = $response->original->getData()['page']['props'];

    expect($props['rows'])->toHaveCount(1)
        ->and($props['rows'][0]['invoice'])->toBe($taken->invoice)
        ->and($props['rows'][0]['label'])->toBe('Ganti LCD')
        ->and($props['rows'][0]['cost'])->toEqual(250000)
        ->and($props['summary'])->toMatchArray(['count' => 1, 'total' => 250000.0, 'unpaid' => 1]);
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

test('laptop lokal export orders items by status, brand then name', function () {
    $lenovo = Brand::create(['name' => 'Lenovo']);
    $acer = Brand::create(['name' => 'Acer']);
    $base = ['supplier_id' => null, 'warranty' => '1 tahun', 'stock' => 1, 'sell_price' => 5000];

    Laptop::create([...$base, 'brand_id' => $lenovo->id, 'name' => 'Lenovo B', 'sku' => 'LPT-1', 'status' => 'SIAP JUAL']);
    Laptop::create([...$base, 'brand_id' => $acer->id, 'name' => 'Acer Z', 'sku' => 'LPT-2', 'status' => 'DISPLAY']);
    Laptop::create([...$base, 'brand_id' => $acer->id, 'name' => 'Acer A', 'sku' => 'LPT-3', 'status' => 'SIAP JUAL']);
    Laptop::create([...$base, 'brand_id' => $lenovo->id, 'name' => 'Lenovo A', 'sku' => 'LPT-4', 'status' => 'DISPLAY']);
    Laptop::create([...$base, 'brand_id' => $acer->id, 'name' => 'Acer B', 'sku' => 'LPT-5', 'status' => 'DISPLAY']);

    Pdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data) {
            expect(array_column($data['items'], 'name'))
                ->toBe(['Acer A', 'Lenovo B', 'Acer B', 'Acer Z', 'Lenovo A']);

            return true;
        })
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->andReturnSelf();
    Pdf::shouldReceive('download')->andReturn(response('pdf'));

    $this->actingAs($this->user)
        ->get('/stock/laptop/report/export?jenis=lokal&status=jual_display')
        ->assertOk();
});

test('marking a laptop as posted sets display status', function () {
    $laptop = Laptop::create(['brand_id' => $this->brand->id, 'supplier_id' => null, 'warranty' => '1 tahun', 'name' => 'Asus', 'sku' => 'LPT-9', 'code' => 'C9', 'stock' => 1, 'buy_price' => 1000, 'sell_price' => 5000, 'status' => 'SIAP JUAL']);

    $this->actingAs($this->user)->patch("/stock/laptop/{$laptop->id}/post")->assertSessionHas('success');

    expect($laptop->fresh())
        ->is_posted->toBeTrue()
        ->status->toBe('DISPLAY');
});
