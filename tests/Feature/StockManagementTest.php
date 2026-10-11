<?php

use App\Models\Accessory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Gadget;
use App\Models\Laptop;
use App\Models\Sparepart;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Arr;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
    $this->brand = Brand::create(['name' => 'Asus']);
    $this->category = Category::create(['name' => 'Layar']);
});

test('laptop can be created, updated and deleted', function () {
    $payload = [
        'brand_id' => $this->brand->id,
        'name' => 'ROG Strix G16',
        'warranty' => '1 tahun',
        'stock' => 2,
        'status' => 'SIAP JUAL',
    ];

    $this->actingAs($this->user)->post('/stock/laptop', $payload)
        ->assertSessionHas('success');
    $laptop = Laptop::where('name', 'ROG Strix G16')->firstOrFail();

    $this->actingAs($this->user)->put("/stock/laptop/{$laptop->id}", [...Arr::except($payload, 'stock'), 'name' => 'ROG Strix G18'])
        ->assertSessionHas('success');
    expect($laptop->fresh()->name)->toBe('ROG Strix G18');
    expect($laptop->fresh()->stock)->toBe(2);

    $this->actingAs($this->user)->put("/stock/laptop/{$laptop->id}", [...Arr::except($payload, 'stock'), 'stock' => 9])
        ->assertSessionHasErrors('stock');
    expect($laptop->fresh()->stock)->toBe(2);

    $this->actingAs($this->user)->delete("/stock/laptop/{$laptop->id}")
        ->assertSessionHas('success');
    expect(Laptop::find($laptop->id))->toBeNull();
});

test('laptop sku is generated automatically and status must be a known value', function () {
    $payload = [
        'brand_id' => $this->brand->id,
        'name' => 'ROG',
        'warranty' => '1 tahun',
        'stock' => 1,
        'status' => 'SIAP JUAL',
        'accessories' => ['Charger'],
        'features' => ['Backlit keyboard'],
        'specification' => ['cpu' => 'Ryzen 7', 'gpu' => 'RTX 3050'],
        'issue_notes' => ['Baterai drop'],
    ];

    $this->actingAs($this->user)->post('/stock/laptop', $payload)->assertSessionHasNoErrors();
    $laptop = Laptop::where('name', 'ROG')->firstOrFail();
    expect($laptop->sku)->toStartWith('LTP-')
        ->and($laptop->accessories)->toBe(['Charger'])
        ->and($laptop->specification)->toMatchArray(['cpu' => 'Ryzen 7']);

    $this->actingAs($this->user)->post('/stock/laptop', [...$payload, 'status' => 'ENTAH'])
        ->assertSessionHasErrors('status');
});

test('gadget sku is generated automatically', function () {
    $payload = [
        'brand_id' => $this->brand->id,
        'name' => 'Samsung Galaxy A55',
        'warranty' => '1 tahun',
        'stock' => 3,
        'status' => 'READY STOCK',
    ];

    $this->actingAs($this->user)->post('/stock/gadget', $payload)
        ->assertSessionHas('success');
    expect(Gadget::where('name', 'Samsung Galaxy A55')->firstOrFail()->sku)->toStartWith('GDT-');
});

test('accessory sku is generated automatically', function () {
    $payload = [
        'brand_id' => $this->brand->id,
        'name' => 'Charger 20W',
        'warranty' => '1 tahun',
        'stock' => 5,
        'status' => 'READY STOCK',
    ];

    $this->actingAs($this->user)->post('/stock/aksesoris', $payload)
        ->assertSessionHas('success');
    expect(Accessory::where('name', 'Charger 20W')->firstOrFail()->sku)->toStartWith('ACC-');
});

test('sku is generated sequentially per module', function () {
    Laptop::create(['brand_id' => $this->brand->id, 'name' => 'ROG 1', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'SIAP JUAL']);
    Laptop::create(['brand_id' => $this->brand->id, 'name' => 'ROG 2', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'SIAP JUAL']);
    Accessory::create(['brand_id' => $this->brand->id, 'name' => 'Tas', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);

    expect(Laptop::orderBy('id')->pluck('sku')->all())->toBe(['LTP-0001', 'LTP-0002'])
        ->and(Accessory::firstOrFail()->sku)->toBe('ACC-0001');
});

test('sparepart requires category and generates sku automatically', function () {
    $payload = [
        'category_id' => $this->category->id,
        'brand_id' => $this->brand->id,
        'name' => 'LCD iPhone 14 Pro',
        'warranty' => '1 tahun',
        'stock' => 1,
        'notes' => '',
        'status' => 'READY STOCK',
    ];

    $this->actingAs($this->user)->post('/stock/sparepart', $payload)
        ->assertSessionHas('success');
    expect(Sparepart::where('name', 'LCD iPhone 14 Pro')->firstOrFail()->sku)->toStartWith('SPT-');

    $this->actingAs($this->user)->post('/stock/sparepart', [...$payload, 'name' => 'LCD 2', 'category_id' => null])
        ->assertSessionHasErrors('category_id');
});

test('stock detail page renders for each big4 module', function () {
    $laptop = Laptop::create(['brand_id' => $this->brand->id, 'name' => 'ROG', 'sku' => 'LPT-009', 'warranty' => '1 tahun', 'stock' => 2, 'status' => 'SIAP JUAL']);
    $gadget = Gadget::create(['specification' => [], 'brand_id' => $this->brand->id, 'name' => 'Mouse', 'sku' => 'GDG-009', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    $accessory = Accessory::create(['brand_id' => $this->brand->id, 'name' => 'Tas', 'sku' => 'ACC-009', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    $sparepart = Sparepart::create(['brand_id' => $this->brand->id, 'category_id' => $this->category->id, 'name' => 'Keyboard', 'sku' => 'SPR-009', 'warranty' => '1 tahun', 'stock' => 3, 'notes' => '', 'status' => 'READY STOCK']);

    $this->actingAs($this->user)->get("/stock/laptop/{$laptop->id}")->assertOk();
    $this->actingAs($this->user)->get("/stock/gadget/{$gadget->id}")->assertOk();
    $this->actingAs($this->user)->get("/stock/aksesoris/{$accessory->id}")->assertOk();
    $this->actingAs($this->user)->get("/stock/sparepart/{$sparepart->id}")->assertOk();
});

test('stock detail page is forbidden without show permission', function () {
    $tech = User::factory()->withRole('TECH')->create();
    $laptop = Laptop::create(['brand_id' => $this->brand->id, 'name' => 'ROG', 'sku' => 'LPT-010', 'warranty' => '1 tahun', 'stock' => 2, 'status' => 'SIAP JUAL']);

    $this->actingAs($tech)->get("/stock/laptop/{$laptop->id}")->assertForbidden();
});

test('laptop index filters by name, sku, code or serial, brand and status, sorted by sku', function () {
    $otherBrand = Brand::create(['name' => 'Lenovo']);
    Laptop::create(['brand_id' => $this->brand->id, 'name' => 'Zenbook', 'sku' => 'LPT-AAA111', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'SIAP JUAL']);
    Laptop::create(['brand_id' => $this->brand->id, 'name' => 'Asus ROG', 'sku' => 'LPT-BBB222', 'code' => 'KODE-X9', 'warranty' => '1 tahun', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'HABIS']);
    Laptop::create(['brand_id' => $otherBrand->id, 'name' => 'Legion', 'sku' => 'LPT-CCC333', 'serialnumber' => 'SN-LEG-7', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'SIAP JUAL']);

    $names = fn ($response) => collect($response->original->getData()['page']['props']['laptops']['data'])->pluck('name')->all();

    expect($names($this->actingAs($this->user)->get('/stock/laptop')))->toBe(['Zenbook', 'Asus ROG', 'Legion']);
    expect($names($this->actingAs($this->user)->get('/stock/laptop?sort=desc')))->toBe(['Legion', 'Asus ROG', 'Zenbook']);
    expect($names($this->actingAs($this->user)->get('/stock/laptop?search=LPT-BBB')))->toBe(['Asus ROG']);
    expect($names($this->actingAs($this->user)->get('/stock/laptop?search=KODE-X9')))->toBe(['Asus ROG']);
    expect($names($this->actingAs($this->user)->get('/stock/laptop?search=SN-LEG')))->toBe(['Legion']);
    expect($names($this->actingAs($this->user)->get("/stock/laptop?brand_id={$otherBrand->id}")))->toBe(['Legion']);
    expect($names($this->actingAs($this->user)->get('/stock/laptop?status=SIAP%20JUAL')))->toBe(['Zenbook', 'Legion']);
});

test('gadget index filters by name or sku, brand, and sorts by sku', function () {
    $otherBrand = Brand::create(['name' => 'Samsung']);
    Gadget::create(['specification' => [], 'brand_id' => $this->brand->id, 'name' => 'Mouse Gaming', 'sku' => 'GDG-BBB', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    Gadget::create(['specification' => [], 'brand_id' => $this->brand->id, 'name' => 'Keyboard', 'sku' => 'GDG-AAA', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    Gadget::create(['specification' => [], 'brand_id' => $otherBrand->id, 'name' => 'Galaxy Buds', 'sku' => 'GDG-CCC', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);

    $names = fn ($response) => collect($response->original->getData()['page']['props']['gadgets']['data'])->pluck('name')->all();

    expect($names($this->actingAs($this->user)->get('/stock/gadget')))->toBe(['Keyboard', 'Mouse Gaming', 'Galaxy Buds']);
    expect($names($this->actingAs($this->user)->get('/stock/gadget?sort=desc')))->toBe(['Galaxy Buds', 'Mouse Gaming', 'Keyboard']);
    expect($names($this->actingAs($this->user)->get('/stock/gadget?search=GDG-BBB')))->toBe(['Mouse Gaming']);
    expect($names($this->actingAs($this->user)->get('/stock/gadget?search=Galaxy')))->toBe(['Galaxy Buds']);
    expect($names($this->actingAs($this->user)->get("/stock/gadget?brand_id={$otherBrand->id}")))->toBe(['Galaxy Buds']);
});

test('accessory index filters by name or sku, brand, and sorts by sku', function () {
    $otherBrand = Brand::create(['name' => 'Anker']);
    Accessory::create(['brand_id' => $this->brand->id, 'name' => 'Charger 20W', 'sku' => 'AKS-BBB', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    Accessory::create(['brand_id' => $this->brand->id, 'name' => 'Tas Laptop', 'sku' => 'AKS-AAA', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    Accessory::create(['brand_id' => $otherBrand->id, 'name' => 'Powerbank', 'sku' => 'AKS-CCC', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);

    $names = fn ($response) => collect($response->original->getData()['page']['props']['accessories']['data'])->pluck('name')->all();

    expect($names($this->actingAs($this->user)->get('/stock/aksesoris')))->toBe(['Tas Laptop', 'Charger 20W', 'Powerbank']);
    expect($names($this->actingAs($this->user)->get('/stock/aksesoris?sort=desc')))->toBe(['Powerbank', 'Charger 20W', 'Tas Laptop']);
    expect($names($this->actingAs($this->user)->get('/stock/aksesoris?search=AKS-CCC')))->toBe(['Powerbank']);
    expect($names($this->actingAs($this->user)->get("/stock/aksesoris?brand_id={$otherBrand->id}")))->toBe(['Powerbank']);
});

test('sparepart index filters by category and brand, and sorts by sku', function () {
    $otherCategory = Category::create(['name' => 'Baterai']);
    Sparepart::create(['brand_id' => $this->brand->id, 'category_id' => $this->category->id, 'name' => 'Keyboard', 'sku' => 'SPR-BBB', 'warranty' => '1 tahun', 'stock' => 1, 'notes' => '', 'status' => 'READY STOCK']);
    Sparepart::create(['brand_id' => $this->brand->id, 'category_id' => $this->category->id, 'name' => 'LCD', 'sku' => 'SPR-AAA', 'warranty' => '1 tahun', 'stock' => 1, 'notes' => '', 'status' => 'READY STOCK']);
    Sparepart::create(['brand_id' => $this->brand->id, 'category_id' => $otherCategory->id, 'name' => 'Battery', 'sku' => 'SPR-CCC', 'warranty' => '1 tahun', 'stock' => 1, 'notes' => '', 'status' => 'READY STOCK']);

    $names = fn ($response) => collect($response->original->getData()['page']['props']['spareparts']['data'])->pluck('name')->all();

    expect($names($this->actingAs($this->user)->get('/stock/sparepart')))->toBe(['LCD', 'Keyboard', 'Battery']);
    expect($names($this->actingAs($this->user)->get('/stock/sparepart?sort=desc')))->toBe(['Battery', 'Keyboard', 'LCD']);
    expect($names($this->actingAs($this->user)->get('/stock/sparepart?search=Keyboard')))->toBe(['Keyboard']);
    expect($names($this->actingAs($this->user)->get("/stock/sparepart?category_id={$otherCategory->id}")))->toBe(['Battery']);
});

test('laptop index filters by supplier', function () {
    $supplier = Supplier::create(['name' => 'PT Maju Jaya']);
    Laptop::create(['brand_id' => $this->brand->id, 'supplier_id' => $supplier->id, 'name' => 'Zenbook', 'sku' => 'LPT-SUP1', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'SIAP JUAL']);
    Laptop::create(['brand_id' => $this->brand->id, 'name' => 'ROG', 'sku' => 'LPT-SUP2', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'SIAP JUAL']);

    $names = fn ($response) => collect($response->original->getData()['page']['props']['laptops']['data'])->pluck('name')->all();

    expect($names($this->actingAs($this->user)->get("/stock/laptop?supplier_id={$supplier->id}")))->toBe(['Zenbook']);
});

test('gadget, accessory and sparepart index filter by supplier and status', function () {
    $supplier = Supplier::create(['name' => 'CV Sentosa']);
    Gadget::create(['specification' => [], 'brand_id' => $this->brand->id, 'supplier_id' => $supplier->id, 'name' => 'Mouse', 'sku' => 'GDG-SUP', 'warranty' => '1 tahun', 'stock' => 1, 'status' => 'READY STOCK']);
    Gadget::create(['specification' => [], 'brand_id' => $this->brand->id, 'name' => 'Webcam', 'sku' => 'GDG-NOS', 'warranty' => '1 tahun', 'stock' => 0, 'status' => 'HABIS']);
    expect(collect($this->actingAs($this->user)->get("/stock/gadget?supplier_id={$supplier->id}")->original->getData()['page']['props']['gadgets']['data'])->pluck('name')->all())->toBe(['Mouse']);
    expect(collect($this->actingAs($this->user)->get('/stock/gadget?status=HABIS')->original->getData()['page']['props']['gadgets']['data'])->pluck('name')->all())->toBe(['Webcam']);

    Accessory::create(['brand_id' => $this->brand->id, 'name' => 'Tas', 'sku' => 'AKS-HBS', 'warranty' => '1 tahun', 'stock' => 0, 'status' => 'HABIS']);
    expect(collect($this->actingAs($this->user)->get('/stock/aksesoris?status=HABIS')->original->getData()['page']['props']['accessories']['data'])->pluck('name')->all())->toBe(['Tas']);

    Sparepart::create(['brand_id' => $this->brand->id, 'category_id' => $this->category->id, 'supplier_id' => $supplier->id, 'name' => 'LCD', 'sku' => 'SPR-SUP', 'warranty' => '1 tahun', 'stock' => 1, 'notes' => '', 'status' => 'READY STOCK']);
    expect(collect($this->actingAs($this->user)->get("/stock/sparepart?supplier_id={$supplier->id}")->original->getData()['page']['props']['spareparts']['data'])->pluck('name')->all())->toBe(['LCD']);
});
