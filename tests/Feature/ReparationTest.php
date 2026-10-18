<?php

use App\Models\CashFlow;
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
        ->toBe('ON PROCESS. Dalam tahap pengerjaan.');
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

    activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus A416', 'complaint' => ['Tidak bisa booting'], 'status' => 'ON PROCESS']);
    activeReparation(['customer_id' => $otherCustomer->id, 'user_id' => $this->user->id, 'item_name' => 'Acer Aspire', 'flag' => ['SW'], 'complaint' => ['Tidak bisa booting'], 'status' => 'RECEIVED']);

    $response = $this->actingAs($this->user)->get('/servis?status=ON PROCESS&search=Asus');

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    expect($props['reparations']['data'])->toHaveCount(1)
        ->and($props['reparations']['data'][0]['item_name'])->toBe('Asus A416')
        ->and($props['statuses'])->toContain('ON PROCESS');
});

test('index filters by flag', function () {
    activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus A416', 'flag' => ['HW', 'SW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);
    activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Acer Aspire', 'flag' => ['MB'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);

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

test('confirm sets stage items and deadline, moves status to waiting confirmation and logs the status change', function () {
    $reparation = Reparation::create(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'CHECKED']);

    $stage = $reparation->stages()->create(['sequence' => 1, 'status' => 'PENDING']);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/konfirmasi", [
        'stage_id' => $stage->id,
        'items' => [['label' => 'Ganti LCD', 'cost' => '250000', 'est_days' => null]],
        'process_deadline_at' => '2026-10-20T17:00',
        'note' => 'Customer setuju ganti LCD.',
    ])->assertSessionHas('success');

    $fresh = $reparation->fresh();
    expect($fresh->status)->toBe('WAITING CONFIRMATION')
        ->and($fresh->process_deadline_at->format('Y-m-d H:i'))->toBe('2026-10-20 17:00')
        ->and($stage->fresh()->status)->toBe('CONFIRMED')
        ->and($stage->items()->pluck('label')->all())->toBe(['Ganti LCD'])
        ->and((float) $stage->items()->sum('cost'))->toBe(250000.0)
        ->and($reparation->logs()->latest('id')->first()->note)->toBe('Customer setuju ganti LCD.')
        ->and($reparation->logs()->pluck('event')->all())->toContain('STATUS_CHANGED');
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
test('index page includes reparation stages so confirm modal can send stage id', function () {
    $reparation = activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'RECEIVED']);
    $stage = $reparation->stages()->create(['sequence' => 1, 'status' => 'PENDING']);
    $stage->items()->create(['label' => 'Ganti LCD', 'cost' => '500000', 'est_days' => null]);

    $this->actingAs($this->user)->get(route('servis.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Servis/Index')
            ->where('reparations.data.0.stages.0.id', $stage->id)
            ->where('reparations.data.0.stages.0.items.0.label', 'Ganti LCD'));
});
test('index page renders confirmed stage timestamps as formatted dates', function () {
    $reparation = activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus', 'flag' => ['HW'], 'accessories' => [], 'complaint' => ['Mati total'], 'condition' => [], 'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'WAITING CONFIRMATION']);
    $reparation->stages()->create(['sequence' => 1, 'status' => 'CONFIRMED', 'confirmed_at' => now()]);

    $this->actingAs($this->user)->get(route('servis.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Servis/Index')
            ->where('reparations.data.0.stages.0.status', 'CONFIRMED'));
});
test('index lists every reparation that is not done or cancel ordered by nearest deadline', function () {
    $make = fn (string $name, string $status, ?string $check, ?string $process) => Reparation::create([
        'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => $name,
        'flag' => ['HW'], 'accessories' => [], 'complaint' => [], 'condition' => [],
        'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => $status,
        'check_deadline_at' => $check, 'process_deadline_at' => $process,
    ]);

    $make('Lusa', 'ON PROCESS', now()->subDays(3)->toDateTimeString(), now()->addDays(2)->toDateTimeString());
    $make('Besok', 'RECEIVED', now()->addDay()->toDateTimeString(), null);
    $make('Hari ini', 'ON PROCESS', null, now()->toDateTimeString());
    $make('Lewat', 'ON PROCESS', null, now()->subDay()->toDateTimeString());
    $make('Diambil', 'TAKEN', null, null);
    $make('Selesai', 'DONE', null, now()->toDateTimeString());
    $make('Batal', 'CANCEL', null, now()->toDateTimeString());

    $props = $this->actingAs($this->user)->get('/servis')->original->getData()['page']['props'];

    expect(collect($props['reparations']['data'])->pluck('item_name')->all())
        ->toBe(['Lewat', 'Hari ini', 'Besok', 'Lusa', 'Diambil']);
});

test('index search still returns matching reparations', function () {
    activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Asus A416', 'status' => 'TAKEN']);
    activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Acer Aspire']);

    $props = $this->actingAs($this->user)->get('/servis?search=Asus')->original->getData()['page']['props'];

    expect(collect($props['reparations']['data'])->pluck('item_name')->all())->toBe(['Asus A416']);
});

test('index date range filters by the date the reparation was created', function () {
    Reparation::create([
        'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Kemarin',
        'flag' => ['HW'], 'accessories' => [], 'complaint' => [], 'condition' => [],
        'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'ON PROCESS',
        'process_deadline_at' => '2026-10-19 17:00',
    ])->forceFill(['created_at' => '2026-10-17 10:00:00'])->save();
    Reparation::create([
        'customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'item_name' => 'Besok',
        'flag' => ['HW'], 'accessories' => [], 'complaint' => [], 'condition' => [],
        'estimated_cost' => '0', 'technician_notes' => '', 'additional_notes' => '', 'status' => 'ON PROCESS',
        'process_deadline_at' => '2026-10-17 17:00',
    ])->forceFill(['created_at' => '2026-10-19 09:00:00'])->save();

    $props = $this->actingAs($this->user)->get('/servis?from=2026-10-19&to=2026-10-19')->original->getData()['page']['props'];

    expect(collect($props['reparations']['data'])->pluck('item_name')->all())->toBe(['Besok'])
        ->and($props['filters']['from'])->toBe('2026-10-19')
        ->and($props['filters']['to'])->toBe('2026-10-19');

    $all = $this->actingAs($this->user)->get('/servis')->original->getData()['page']['props'];
    expect(collect($all['reparations']['data'])->pluck('item_name')->all())->toBe(['Besok', 'Kemarin'])
        ->and($all['filters']['from'])->toBeNull()
        ->and($all['filters']['to'])->toBeNull();
});

function reparationWithCost(array $attributes, array $items, string $stageStatus = 'CONFIRMED'): Reparation
{
    $reparation = activeReparation($attributes);
    $stage = $reparation->stages()->create(['sequence' => 1, 'status' => $stageStatus]);

    foreach ($items as $item) {
        $stage->items()->create($item);
    }

    return $reparation;
}

test('moving to DONE stores the confirmed total and records cash once when paid', function () {
    $reparation = reparationWithCost(
        ['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'status' => 'ON PROCESS'],
        [['label' => 'Ganti LCD', 'cost' => 750000], ['label' => 'Servis fan', 'cost' => 150000]],
    );

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/status", [
        'status' => 'DONE',
        'payment_status' => 'paid',
    ])->assertSessionHas('success');

    $fresh = $reparation->fresh();
    $flow = CashFlow::query()->where('reference', $fresh->invoice)->sole();

    expect($fresh->status)->toBe('DONE')
        ->and($fresh->payment_status)->toBe('paid')
        ->and((float) $fresh->total_price)->toBe(900000.0)
        ->and($flow->type)->toBe('in')
        ->and($flow->category)->toBe('SERVIS')
        ->and((float) $flow->amount)->toBe(900000.0);
});

test('unpaid DONE records no cash and TAKEN after payment does not record cash twice', function () {
    $reparation = reparationWithCost(
        ['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'status' => 'ON PROCESS'],
        [['label' => 'Ganti LCD', 'cost' => 500000]],
    );

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/status", [
        'status' => 'DONE',
        'payment_status' => 'unpaid',
    ])->assertSessionHas('success');

    expect($reparation->fresh()->payment_status)->toBe('unpaid')
        ->and((float) $reparation->fresh()->total_price)->toBe(500000.0)
        ->and(CashFlow::query()->where('reference', $reparation->invoice)->count())->toBe(0);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/status", [
        'status' => 'TAKEN',
        'payment_status' => 'paid',
    ])->assertSessionHas('success');

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/status", [
        'status' => 'TAKEN',
    ])->assertSessionHas('success');

    expect(CashFlow::query()->where('reference', $reparation->invoice)->count())->toBe(1)
        ->and($reparation->fresh()->status)->toBe('TAKEN')
        ->and($reparation->fresh()->payment_status)->toBe('paid');
});

test('taken reparation can be paid later through the mark paid endpoint', function () {
    $reparation = reparationWithCost(
        ['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'status' => 'ON PROCESS'],
        [['label' => 'Ganti baterai', 'cost' => 300000]],
    );

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/status", ['status' => 'TAKEN']);

    expect($reparation->fresh()->payment_status)->toBe('unpaid')
        ->and((float) $reparation->fresh()->total_price)->toBe(300000.0)
        ->and(CashFlow::query()->count())->toBe(0);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/lunas")->assertSessionHas('success');

    expect($reparation->fresh()->payment_status)->toBe('paid')
        ->and($reparation->fresh()->paid_at)->not->toBeNull()
        ->and(CashFlow::query()->where('reference', $reparation->invoice)->sum('amount'))->toEqual(300000);
});

test('mark paid is rejected before the reparation is done', function () {
    $reparation = activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'status' => 'ON PROCESS']);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/lunas")->assertSessionHas('error');

    expect($reparation->fresh()->payment_status)->toBe('unpaid')
        ->and(CashFlow::query()->count())->toBe(0);
});

test('item cost rejects non numeric values', function () {
    $reparation = activeReparation(['customer_id' => $this->customer->id, 'user_id' => $this->user->id, 'status' => 'CHECKED']);

    $this->actingAs($this->user)->patch("/servis/{$reparation->id}/konfirmasi", [
        'items' => [['label' => 'Ganti LCD', 'cost' => '250rb', 'est_days' => null]],
        'process_deadline_at' => '2026-10-20T17:00',
    ])->assertSessionHasErrors('items.0.cost');
});

function activeReparation(array $attributes): Reparation
{
    return Reparation::create(array_merge([
        'customer_id' => $attributes['customer_id'],
        'user_id' => $attributes['user_id'],
        'item_name' => 'Asus',
        'flag' => ['HW'],
        'accessories' => [],
        'complaint' => [],
        'condition' => [],
        'estimated_cost' => '0',
        'technician_notes' => '',
        'additional_notes' => '',
        'status' => 'RECEIVED',
        'check_deadline_at' => now()->addDay()->toDateTimeString(),
        'process_deadline_at' => now()->addDays(3)->toDateTimeString(),
    ], $attributes));
}
