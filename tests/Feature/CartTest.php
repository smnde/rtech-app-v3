<?php

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Laptop;
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

test('adding the same product twice increments qty and recalculates the cart total on the server', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);

    $cart = Cart::query()->where('user_id', $this->user->id)->where('type', 'sale')->where('status', 'active')->firstOrFail();

    expect($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->qty)->toBe(2)
        ->and((float) $cart->fresh()->total)->toBe(24000000.0)
        ->and($cart->fresh()->item_count)->toBe(2);
});

test('each user has their own active cart', function () {
    $other = User::factory()->withRole('ADMIN')->create();

    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);

    $this->actingAs($other)->get(route('transaksi.keranjang.show', 'sale'))
        ->assertOk()
        ->assertJsonPath('cart.items', []);
});

test('holding a cart stores it as held and starts a fresh active cart', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);

    $this->actingAs($this->user)->post(route('transaksi.keranjang.hold', 'sale'), ['label' => 'Budi nanti']);

    $held = Cart::query()->where('user_id', $this->user->id)->where('status', 'held')->firstOrFail();
    expect($held->label)->toBe('Budi nanti')
        ->and($held->items)->toHaveCount(1);

    $this->actingAs($this->user)->get(route('transaksi.keranjang.show', 'sale'))
        ->assertJsonPath('cart.items', []);
});

test('cannot hold an empty cart', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.hold', 'sale'))
        ->assertStatus(422);
});

test('resuming a held cart restores it when the active cart is empty', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);
    $this->actingAs($this->user)->post(route('transaksi.keranjang.hold', 'sale'));
    $held = Cart::query()->where('status', 'held')->firstOrFail();

    $this->actingAs($this->user)->post(route('transaksi.keranjang.resume', $held))->assertRedirect();

    expect($held->fresh()->status)->toBe('active')
        ->and(Cart::query()->where('user_id', $this->user->id)->where('type', 'sale')->where('status', 'active')->count())
        ->toBe(1);
});

test('a user cannot touch another user\'s cart', function () {
    $other = User::factory()->withRole('ADMIN')->create();
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);
    $this->actingAs($this->user)->post(route('transaksi.keranjang.hold', 'sale'));
    $held = Cart::query()->where('status', 'held')->firstOrFail();

    $this->actingAs($other)->delete(route('transaksi.keranjang.destroy', $held))->assertForbidden();
    expect(Cart::query()->whereKey($held->id)->exists())->toBeTrue();
});

test('updating an item recalculates the total with discount and bonus', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'qty' => 2,
    ]);
    $item = CartItem::query()->firstOrFail();

    $this->actingAs($this->user)->put(route('transaksi.keranjang.item.update', $item), [
        'discount' => 500000,
    ]);

    expect((float) $item->cart->fresh()->total)->toBe(23500000.0);
});

test('a sale cart can be assigned a customer', function () {
    $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '0812', 'is_member' => false]);

    $this->actingAs($this->user)->put(route('transaksi.keranjang.customer', 'sale'), [
        'customer_id' => $customer->id,
    ])->assertRedirect();

    expect(Cart::query()->where('user_id', $this->user->id)->where('type', 'sale')->firstOrFail()->customer_id)
        ->toBe($customer->id);
});

test('a cart for purchases requires the add purchase permission', function () {
    $cashier = User::factory()->create();
    $cashier->givePermissionTo('add sale');

    $this->actingAs($cashier)->get(route('transaksi.keranjang.show', 'sale'))->assertOk();
    $this->actingAs($cashier)->get(route('transaksi.keranjang.show', 'purchase'))->assertForbidden();
});

test('a successful purchase clears the active purchase cart', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'purchase'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'price' => 10000000,
    ]);

    $cart = Cart::activeFor($this->user, 'purchase');
    $this->actingAs($this->user)->post(route('transaksi.pembelian.store'), [
        'payment_status' => 'lunas',
        'status' => 'done',
        'items' => [[
            'item_type' => 'laptop',
            'item_id' => $this->laptop->id,
            'qty' => 1,
            'price' => 10000000,
        ]],
    ])->assertRedirect(route('transaksi.pembelian'));

    $cart->refresh();
    expect($cart->items()->count())->toBe(0)
        ->and((float) $cart->total)->toBe(0.0);
});

test('the stock store flashes the created item so the purchase page can add it to the cart', function () {
    $this->actingAs($this->user)->post(route('stock.laptop.store'), [
        'name' => 'Lenovo Legion 5',
        'brand_id' => $this->laptop->brand_id,
        'warranty' => '1 tahun',
        'stock' => 2,
        'status' => 'SIAP JUAL',
        'buy_price' => 9000000,
        'sell_price' => 11000000,
    ])->assertSessionHas('created_item', fn ($item) => $item['item_type'] === 'laptop' && $item['label'] === 'Lenovo Legion 5');
});

test('an unknown cart type returns not found', function () {
    $this->actingAs($this->user)->get('/transaksi/keranjang/bogus')->assertNotFound();
});

test('sale create page receives the active cart and held carts', function () {
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'qty' => 2,
    ]);

    $this->actingAs($this->user)->get(route('transaksi.penjualan.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Transaksi/PenjualanCreate')
            ->has('cart.items', 1)
            ->where('cart.item_count', 2)
            ->has('heldCarts', 0)
        );
});

test('a successful sale clears the active sale cart', function () {
    $customer = Customer::create(['name' => 'Budi', 'phone' => '081234567890', 'address' => 'Jl. Merdeka 1', 'is_member' => false]);
    $this->actingAs($this->user)->post(route('transaksi.keranjang.item.store', 'sale'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
    ]);
    $this->actingAs($this->user)->put(route('transaksi.keranjang.customer', 'sale'), ['customer_id' => $customer->id]);

    $cart = Cart::activeFor($this->user, 'sale');
    $this->actingAs($this->user)->post(route('transaksi.penjualan.store'), [
        'customer_id' => $customer->id,
        'payment_status' => 'lunas',
        'payment_method' => 'cash',
        'discount' => 0,
        'items' => [[
            'item_type' => 'laptop',
            'item_id' => $this->laptop->id,
            'qty' => 1,
            'price' => 12000000,
            'discount' => 0,
            'is_bonus' => false,
        ]],
    ])->assertSessionHas('success');

    $cart->refresh();
    expect($cart->items()->count())->toBe(0)
        ->and((float) $cart->total)->toBe(0.0)
        ->and($cart->customer_id)->toBeNull();
});

test('creating a customer flashes the new customer for the sale page to select', function () {
    $this->actingAs($this->user)->post(route('customers.store'), [
        'name' => 'Budi Santoso',
        'phone' => '081234567890',
        'address' => 'Jl. Merdeka 1',
        'is_member' => false,
    ])->assertSessionHas('customer', fn ($customer) => $customer['name'] === 'Budi Santoso');
});
