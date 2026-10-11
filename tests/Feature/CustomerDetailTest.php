<?php

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
    $this->customer = Customer::create(['name' => 'Andi Pratama', 'phone' => '08123', 'is_member' => false]);
});

test('customer detail page lists notes and transactions', function () {
    CustomerNote::create(['customer_id' => $this->customer->id, 'type' => 'hutang', 'status' => 'pending', 'description' => 'Hutang tas']);
    Sale::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'invoice' => 'INV-TEST-0001', 'subtotal' => 100000, 'discount' => 0, 'total' => 100000, 'payment_status' => 'hutang']);
    Purchase::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'invoice' => 'BLI-TEST-0001', 'total' => 50000, 'payment_status' => 'lunas', 'po_type' => 'non_po']);

    $response = $this->actingAs($this->user)->get("/customers/{$this->customer->id}");

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    expect($props['notes'])->toHaveCount(1)
        ->and($props['notes'][0]['description'])->toBe('Hutang tas')
        ->and($props['transactions'])->toHaveCount(2)
        ->and(collect($props['transactions'])->pluck('reference')->all())->toContain('INV-TEST-0001', 'BLI-TEST-0001');
});

test('customer can add and toggle note status', function () {
    $this->actingAs($this->user)->post("/customers/{$this->customer->id}/notes", [
        'type' => 'hutang',
        'description' => 'Hutang tas Rp 200.000',
    ])->assertSessionHas('success');

    $note = CustomerNote::firstOrFail();
    expect($note->status)->toBe('pending');

    $this->actingAs($this->user)->put("/customers/{$this->customer->id}/notes/{$note->id}", [
        'type' => 'hutang',
        'description' => 'Hutang tas Rp 200.000',
        'status' => 'done',
    ])->assertSessionHas('success');

    expect($note->fresh()->status)->toBe('done');
});

test('customer note rejects unknown type', function () {
    $this->actingAs($this->user)->post("/customers/{$this->customer->id}/notes", [
        'type' => 'rahasia',
        'description' => 'x',
    ])->assertSessionHasErrors('type');
});
