<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
});

test('brand can be created, updated and deleted', function () {
    $this->actingAs($this->user)->post('/master/brand', ['name' => 'Asus'])
        ->assertRedirect()->assertSessionHas('success');
    $brand = Brand::where('name', 'Asus')->firstOrFail();

    $this->actingAs($this->user)->put("/master/brand/{$brand->id}", ['name' => 'ASUS ROG'])
        ->assertRedirect()->assertSessionHas('success');
    expect($brand->fresh()->name)->toBe('ASUS ROG');

    $this->actingAs($this->user)->delete("/master/brand/{$brand->id}")
        ->assertRedirect()->assertSessionHas('success');
    expect(Brand::find($brand->id))->toBeNull();
});

test('duplicate brand name is rejected', function () {
    Brand::create(['name' => 'Lenovo']);
    $this->actingAs($this->user)->post('/master/brand', ['name' => 'Lenovo'])
        ->assertSessionHasErrors('name');
});

test('brand name must be at least three characters', function () {
    $this->actingAs($this->user)->post('/master/brand', ['name' => 'AB'])
        ->assertSessionHasErrors('name');
});

test('category can be created, updated and deleted', function () {
    $this->actingAs($this->user)->post('/master/category', ['name' => 'Laptop'])
        ->assertRedirect()->assertSessionHas('success');
    $category = Category::where('name', 'Laptop')->firstOrFail();

    $this->actingAs($this->user)->put("/master/category/{$category->id}", ['name' => 'Notebook'])
        ->assertRedirect();
    expect($category->fresh()->name)->toBe('Notebook');

    $this->actingAs($this->user)->delete("/master/category/{$category->id}")
        ->assertRedirect()->assertSessionHas('success');
    expect(Category::find($category->id))->toBeNull();
});

test('duplicate category name is rejected', function () {
    Category::create(['name' => 'Gadget']);
    $this->actingAs($this->user)->post('/master/category', ['name' => 'Gadget'])
        ->assertSessionHasErrors('name');
});

test('supplier can be created with optional fields and deleted', function () {
    $this->actingAs($this->user)->post('/master/supplier', ['name' => 'PT Maju', 'phone' => null, 'description' => null])
        ->assertRedirect()->assertSessionHas('success');
    $supplier = Supplier::where('name', 'PT Maju')->firstOrFail();

    $this->actingAs($this->user)->delete("/master/supplier/{$supplier->id}")
        ->assertRedirect()->assertSessionHas('success');
    expect(Supplier::find($supplier->id))->toBeNull();
});

test('duplicate supplier name is rejected', function () {
    Supplier::create(['name' => 'PT Jaya']);
    $this->actingAs($this->user)->post('/master/supplier', ['name' => 'PT Jaya'])
        ->assertSessionHasErrors('name');
});

test('customer can be created and updated with member flag', function () {
    $this->actingAs($this->user)->post('/customers', [
        'name' => 'Siti Aminah', 'phone' => '081234567890', 'address' => 'Jl. Merdeka', 'is_member' => 1,
    ])->assertRedirect()->assertSessionHas('success');

    $customer = Customer::where('name', 'Siti Aminah')->firstOrFail();
    expect((bool) $customer->is_member)->toBeTrue();

    $this->actingAs($this->user)->put("/customers/{$customer->id}", [
        'name' => 'Siti Aminah', 'phone' => '081234567890', 'address' => null, 'is_member' => 0,
    ])->assertRedirect();
    expect($customer->fresh()->is_member)->toBeFalse();
});

test('customer requires phone', function () {
    $this->actingAs($this->user)->post('/customers', [
        'name' => 'Tanpa HP', 'is_member' => 0,
    ])->assertSessionHasErrors('phone');
});

test('customer delete succeeds when no transactions reference it', function () {
    $customer = Customer::create(['name' => 'Budi', 'phone' => '0812', 'is_member' => false]);
    $this->actingAs($this->user)->delete("/customers/{$customer->id}")
        ->assertRedirect()->assertSessionHas('success');
    expect(Customer::find($customer->id))->toBeNull();
});

test('customer index filters by member status', function () {
    Customer::create(['name' => 'Ani', 'phone' => '0811', 'is_member' => true]);
    Customer::create(['name' => 'Beni', 'phone' => '0822', 'is_member' => false]);

    $this->actingAs($this->user)->get('/customers?member=1')
        ->assertInertia(fn ($page) => $page
            ->where('filters.member', '1')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Ani'));

    $this->actingAs($this->user)->get('/customers?member=0')
        ->assertInertia(fn ($page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Beni'));
});
