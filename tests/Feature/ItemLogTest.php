<?php

use App\Models\Brand;
use App\Models\ItemLog;
use App\Models\Laptop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
    $this->laptop = Laptop::create([
        'brand_id' => Brand::create(['name' => 'Asus'])->id,
        'name' => 'ROG Strix G16',
        'sku' => 'LTP-0001',
        'warranty' => '1 tahun',
        'stock' => 1,
        'status' => 'ON PROSES',
        'buy_price' => 10000000,
        'sell_price' => 12000000,
        'specification' => ['cpu' => 'Intel i5', 'memory' => '8GB'],
    ]);
});

test('upgrade log merges changed specs into laptop specification', function () {
    $this->actingAs($this->user)
        ->post(route('stock.laptop.logs.store', $this->laptop), [
            'type' => 'upgrade',
            'description' => 'RAM diupgrade',
            'memory' => '16GB',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $log = ItemLog::firstOrFail();

    expect($log->item_type)->toBe('laptop')
        ->and($log->item_id)->toBe($this->laptop->id)
        ->and($log->spec_before)->toBe(['cpu' => 'Intel i5', 'memory' => '8GB'])
        ->and($log->spec_after)->toBe(['memory' => '16GB'])
        ->and($this->laptop->fresh()->specification)->toBe(['cpu' => 'Intel i5', 'memory' => '16GB']);
});

test('repair log updates laptop status', function () {
    $this->actingAs($this->user)
        ->post(route('stock.laptop.logs.store', $this->laptop), [
            'type' => 'repair',
            'description' => 'Layar diganti',
            'status_after' => 'KENDALA',
        ])
        ->assertRedirect();

    $log = ItemLog::firstOrFail();

    expect($log->status_before)->toBe('ON PROSES')
        ->and($log->status_after)->toBe('KENDALA')
        ->and($this->laptop->fresh()->status)->toBe('KENDALA');
});

test('repair log requires a status after repair', function () {
    $this->actingAs($this->user)
        ->post(route('stock.laptop.logs.store', $this->laptop), [
            'type' => 'repair',
            'description' => 'Layar diganti',
        ])
        ->assertSessionHasErrors('status_after');

    expect(ItemLog::count())->toBe(0);
});

test('laptop issue can be added and toggled solved with a log entry', function () {
    $this->actingAs($this->user)
        ->post(route('stock.laptop.issues.store', $this->laptop), ['text' => 'Keyboard rusak'])
        ->assertSessionHas('success');

    $issue = $this->laptop->fresh()->issues()[0];

    expect($issue['text'])->toBe('Keyboard rusak')
        ->and($issue['solved'])->toBeFalse();

    $this->actingAs($this->user)
        ->patch(route('stock.laptop.issues.toggle', [$this->laptop, $issue['id']]))
        ->assertSessionHas('success', 'Kendala ditandai selesai.');

    $solved = $this->laptop->fresh()->issues()[0];

    expect($solved['solved'])->toBeTrue()
        ->and($solved['solved_at'])->not->toBeNull()
        ->and(ItemLog::orderBy('id')->pluck('description')->all())->toBe(['Kendala baru: Keyboard rusak', 'Kendala selesai: Keyboard rusak']);
});

test('toggling an unknown laptop issue returns not found', function () {
    $this->actingAs($this->user)
        ->patch(route('stock.laptop.issues.toggle', [$this->laptop, 'tidak-ada']))
        ->assertNotFound();
});

test('repair log does not overwrite existing issues', function () {
    $this->actingAs($this->user)
        ->post(route('stock.laptop.issues.store', $this->laptop), ['text' => 'Baterai drop']);

    $this->actingAs($this->user)
        ->post(route('stock.laptop.logs.store', $this->laptop), [
            'type' => 'repair',
            'description' => 'Layar diganti',
            'status_after' => 'ON PROSES',
        ]);

    expect($this->laptop->fresh()->issues())->toHaveCount(1);
});

test('laptop update keeps solved state of existing issues', function () {
    $this->actingAs($this->user)
        ->post(route('stock.laptop.issues.store', $this->laptop), ['text' => 'Baterai drop']);
    $issue = $this->laptop->fresh()->issues()[0];
    $this->actingAs($this->user)
        ->patch(route('stock.laptop.issues.toggle', [$this->laptop, $issue['id']]));

    $this->actingAs($this->user)
        ->put(route('stock.laptop.update', $this->laptop), [
            'brand_id' => $this->laptop->brand_id,
            'name' => 'ROG Strix G16',
            'warranty' => '1 tahun',
            'status' => 'ON PROSES',
            'issue_notes' => ['Baterai drop', 'Keyboard rusak'],
        ])
        ->assertSessionHasNoErrors();

    $issues = $this->laptop->fresh()->issues();

    expect($issues)->toHaveCount(2)
        ->and($issues[0]['id'])->toBe($issue['id'])
        ->and($issues[0]['solved'])->toBeTrue()
        ->and($issues[1]['text'])->toBe('Keyboard rusak')
        ->and($issues[1]['solved'])->toBeFalse();
});

test('only the author or owner or sysadmin can delete a log', function () {
    $log = ItemLog::create([
        'item_type' => $this->laptop->getMorphClass(),
        'item_id' => $this->laptop->id,
        'user_id' => User::factory()->withRole('ADMIN')->create()->id,
        'type' => 'note',
        'description' => 'Catatan',
    ]);

    $this->actingAs($this->user)
        ->delete(route('item-logs.destroy', $log))
        ->assertForbidden();

    $this->actingAs(User::factory()->withRole('OWNER')->create())
        ->delete(route('item-logs.destroy', $log))
        ->assertRedirect();

    expect(ItemLog::count())->toBe(0);
});
