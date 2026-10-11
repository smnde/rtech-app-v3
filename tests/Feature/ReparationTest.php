<?php

use App\Models\Customer;
use App\Models\Reparation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->withRole('ADMIN')->create();
    $this->customer = Customer::create(['name' => 'Andi Pratama', 'phone' => '08123', 'is_member' => false]);
});

test('store creates reparation with RECEIVED status and a log entry', function () {
    $this->actingAs($this->user)->post('/servis', [
        'customer_id' => $this->customer->id,
        'item_name' => 'Lenovo IdeaPad 3',
        'flag' => ['HW', 'SW'],
        'accessories' => ['Charger', 'Tas'],
        'complaint' => ['Tidak bisa booting'],
        'condition' => ['Baret di layar'],
        'estimated_cost' => '0',
        'check_deadline_at' => '2026-10-12T10:00',
        'process_deadline_at' => '2026-10-19T17:00',
    ])->assertSessionHas('success');

    $reparation = Reparation::firstOrFail();
    expect($reparation->status)->toBe('RECEIVED')
        ->and($reparation->user_id)->toBe($this->user->id)
        ->and($reparation->flag)->toBe(['HW', 'SW'])
        ->and($reparation->accessories)->toBe(['Charger', 'Tas'])
        ->and($reparation->complaint)->toBe(['Tidak bisa booting'])
        ->and($reparation->estimated_cost)->toBe('0')
        ->and($reparation->check_deadline_at->format('Y-m-d H:i'))->toBe('2026-10-12 10:00')
        ->and($reparation->process_deadline_at->format('Y-m-d H:i'))->toBe('2026-10-19 17:00')
        ->and($reparation->logs)->toHaveCount(1)
        ->and($reparation->logs->first()->type)->toBe('status');
});

test('store rejects unknown flag and missing customer', function () {
    $this->actingAs($this->user)->post('/servis', [
        'item_name' => 'Asus',
        'flag' => ['XX'],
        'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '',
    ])->assertSessionHasErrors(['customer_id', 'flag.0']);
});

test('update changes status and logs the transition', function () {
    $reparation = Reparation::create([
        'customer_id' => $this->customer->id,
        'user_id' => $this->user->id,
        'item_name' => 'Asus A416',
        'flag' => ['MB'],
        'accessories' => [],
        'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '',
        'status' => 'RECEIVED',
    ]);

    $this->actingAs($this->user)->put("/servis/{$reparation->id}", [
        'item_name' => 'Asus A416',
        'flag' => ['MB'],
        'accessories' => [],
        'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '',
        'status' => 'ON PROCESS',
    ])->assertSessionHas('success');

    expect($reparation->fresh()->status)->toBe('ON PROCESS')
        ->and($reparation->logs()->latest('id')->first()->description)
        ->toBe('Status berubah dari RECEIVED ke ON PROCESS.');
});

test('update rejects status outside the allowed list', function () {
    $reparation = Reparation::create([
        'customer_id' => $this->customer->id,
        'user_id' => $this->user->id,
        'item_name' => 'Asus A416',
        'flag' => ['MB'],
        'accessories' => [],
        'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '',
        'status' => 'RECEIVED',
    ]);

    $this->actingAs($this->user)->put("/servis/{$reparation->id}", [
        'item_name' => 'Asus A416',
        'flag' => ['MB'],
        'accessories' => [],
        'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '',
        'status' => 'SELESAI',
    ])->assertSessionHasErrors('status');
});

test('index filters by status and search term', function () {
    $otherCustomer = Customer::create(['name' => 'Budi Santoso', 'phone' => '08999', 'is_member' => false]);

    Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus A416', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'DONE']);
    Reparation::create(['customer_id' => $otherCustomer->id, 'user_id' => $this->user->id, 'item_name' => 'Acer Aspire', 'flag' => ['SW'], 'accessories' => [], 'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);

    $response = $this->actingAs($this->user)->get('/servis?status=DONE&search=Asus');

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    expect($props['reparations']['data'])->toHaveCount(1)
        ->and($props['reparations']['data'][0]['item_name'])->toBe('Asus A416')
        ->and($props['statuses'])->toContain('ON PROCESS');
});

test('index filters by flag', function () {
    Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus A416', 'flag' => ['HW', 'SW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);
    Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Acer Aspire', 'flag' => ['MB'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);

    $response = $this->actingAs($this->user)->get('/servis?flag=MB');

    $props = $response->original->getData()['page']['props'];
    expect($props['reparations']['data'])->toHaveCount(1)
        ->and($props['reparations']['data'][0]['item_name'])->toBe('Acer Aspire');
});

test('destroy deletes reparation', function () {
    $reparation = Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Tidak bisa booting'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);

    $this->actingAs($this->user)->delete("/servis/{$reparation->id}")->assertSessionHas('success');

    expect(Reparation::find($reparation->id))->toBeNull();
});

test('confirm sets estimate and deadline, moves status to waiting confirmation and logs both changes', function () {
    $reparation = Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'CHECKED']);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/konfirmasi", [
        'estimated_cost' => '250rb',
        'process_deadline_at' => '2026-10-20T17:00',
    ])->assertSessionHas('success');

    $fresh = $reparation->fresh();
    expect($fresh->status)->toBe('WAITING CONFIRMATION')
        ->and($fresh->estimated_cost)->toBe('250rb')
        ->and($fresh->process_deadline_at->format('Y-m-d H:i'))->toBe('2026-10-20 17:00')
        ->and($reparation->logs()->pluck('event')->all())->toContain('ESTIMATE_CHANGED', 'DEADLINE_CHANGED', 'STATUS_CHANGED');
});

test('change status records from and to values with the note', function () {
    $reparation = Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'ON PROCESS']);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/status", [
        'status' => 'CANCEL',
        'note' => 'Customer batal',
    ])->assertSessionHas('success');

    $log = $reparation->logs()->latest('id')->first();
    expect($reparation->fresh()->status)->toBe('CANCEL')
        ->and($log->from_value)->toBe('ON PROCESS')
        ->and($log->to_value)->toBe('CANCEL')
        ->and($log->note)->toBe('Customer batal');
});

test('show page renders reparation with its logs', function () {
    $reparation = Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);

    $this->actingAs($this->user)->get(route('servis.show', $reparation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Servis/Detail')->has('reparation.logs'));
});
