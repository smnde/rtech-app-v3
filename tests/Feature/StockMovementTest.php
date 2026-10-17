<?php

use App\Models\Brand;
use App\Models\Laptop;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

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

test('stock movement is recorded with the chosen movement date', function () {
    $this->actingAs($this->user)->post(route('stock-movements.store'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'type' => 'in',
        'status' => 'REFUND',
        'movement_date' => '2026-10-01',
        'quantity' => 2,
    ])->assertSessionHas('success');

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->movement_date->toDateString())->toBe('2026-10-01')
        ->and($this->laptop->fresh()->stock)->toBe(5);
});

test('stock movement requires a movement date', function () {
    $this->actingAs($this->user)->post(route('stock-movements.store'), [
        'item_type' => 'laptop',
        'item_id' => $this->laptop->id,
        'type' => 'in',
        'status' => 'REFUND',
        'quantity' => 2,
    ])->assertSessionHasErrors('movement_date');

    expect(StockMovement::query()->count())->toBe(0);
});

test('stock movement index filters by movement date instead of when it was entered', function () {
    Carbon::setTestNow('2026-10-15 09:00:00');

    StockMovement::create([
        'item_type' => $this->laptop->getMorphClass(),
        'item_id' => $this->laptop->id,
        'user_id' => $this->user->id,
        'type' => 'in',
        'status' => 'REFUND',
        'movement_date' => '2026-10-01',
        'quantity' => 1,
        'stock_before' => 3,
        'stock_after' => 4,
    ]);

    $this->actingAs($this->user)
        ->get(route('stock-movements.index', ['from' => '2026-10-15', 'to' => '2026-10-15']))
        ->assertInertia(fn ($page) => $page->has('movements.data', 0));

    $this->actingAs($this->user)
        ->get(route('stock-movements.index', ['from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertInertia(fn ($page) => $page->has('movements.data', 1)
            ->where('movements.data.0.date', '2026-10-01'));

    Carbon::setTestNow();
});

test('stock movement date and notes can be edited without changing stock', function () {
    $movement = StockMovement::create([
        'item_type' => $this->laptop->getMorphClass(),
        'item_id' => $this->laptop->id,
        'user_id' => $this->user->id,
        'type' => 'in',
        'status' => 'REFUND',
        'movement_date' => '2026-10-01',
        'quantity' => 2,
        'stock_before' => 3,
        'stock_after' => 5,
    ]);
    $this->laptop->update(['stock' => 5]);

    $this->actingAs($this->user)->patch(route('stock-movements.update', $movement), [
        'movement_date' => '2026-10-05',
        'notes' => 'Koreksi nota',
    ])->assertSessionHas('success');

    expect($movement->fresh())
        ->movement_date->toDateString()->toBe('2026-10-05')
        ->notes->toBe('Koreksi nota');
    expect($this->laptop->fresh()->stock)->toBe(5);
});

test('deleting a stock movement reverses its effect on the item stock', function () {
    $movement = StockMovement::create([
        'item_type' => $this->laptop->getMorphClass(),
        'item_id' => $this->laptop->id,
        'user_id' => $this->user->id,
        'type' => 'in',
        'status' => 'REFUND',
        'movement_date' => '2026-10-01',
        'quantity' => 2,
        'stock_before' => 3,
        'stock_after' => 5,
    ]);
    $this->laptop->update(['stock' => 5]);

    $this->actingAs($this->user)->delete(route('stock-movements.destroy', $movement))
        ->assertSessionHas('success');

    expect(StockMovement::query()->count())->toBe(0)
        ->and($this->laptop->fresh()->stock)->toBe(3);
});

test('deleting a stock movement is refused when it would make stock negative', function () {
    $movement = StockMovement::create([
        'item_type' => $this->laptop->getMorphClass(),
        'item_id' => $this->laptop->id,
        'user_id' => $this->user->id,
        'type' => 'in',
        'status' => 'REFUND',
        'movement_date' => '2026-10-01',
        'quantity' => 2,
        'stock_before' => 3,
        'stock_after' => 5,
    ]);
    $this->laptop->update(['stock' => 1]);

    $this->actingAs($this->user)->delete(route('stock-movements.destroy', $movement))
        ->assertSessionHasErrors('stock');

    expect(StockMovement::query()->count())->toBe(1)
        ->and($this->laptop->fresh()->stock)->toBe(1);
});
