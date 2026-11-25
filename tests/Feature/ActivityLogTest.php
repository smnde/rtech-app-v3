<?php

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Laptop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

test('creating a customer is recorded in the activity log', function () {
    $user = User::factory()->withRole('ADMIN')->create();

    $this->actingAs($user)
        ->post('/customers', [
            'name' => 'Budi',
            'phone' => '0812345678',
            'is_member' => false,
        ])
        ->assertSessionHas('success');

    $log = ActivityLog::query()->where('module', 'customer')->sole();
    $customer = Customer::query()->where('name', 'Budi')->sole();

    expect($log->action)->toBe(ActivityLog::ACTION_CREATED)
        ->and($log->description)->toBe('Membuat Customer Budi')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->subject_type)->toBe($customer->getMorphClass())
        ->and($log->subject_id)->toBe($customer->id);
});

test('updating a customer records the changed attribute with its old value', function () {
    $user = User::factory()->withRole('ADMIN')->create();
    $customer = Customer::create(['name' => 'Budi', 'phone' => '0812345678', 'is_member' => true]);

    $this->actingAs($user)
        ->put("/customers/{$customer->id}", [
            'name' => 'Budi Santoso',
            'phone' => '0812345678',
            'is_member' => true,
        ])
        ->assertSessionHas('success');

    $log = ActivityLog::query()->where('module', 'customer')->latest('id')->firstOrFail();

    expect($log->action)->toBe(ActivityLog::ACTION_UPDATED)
        ->and($log->description)->toBe('Mengubah Customer Budi Santoso — name: Budi → Budi Santoso');
});

test('deleting a customer is recorded in the activity log', function () {
    $sysadmin = User::factory()->withRole('SYSADMIN')->create();
    $customer = Customer::create(['name' => 'Budi', 'phone' => '0812345678', 'is_member' => false]);

    $this->actingAs($sysadmin)
        ->delete("/customers/{$customer->id}", ['password' => 'password'])
        ->assertRedirect();

    $log = ActivityLog::query()->where('module', 'customer')->latest('id')->firstOrFail();

    expect($log->action)->toBe(ActivityLog::ACTION_DELETED)
        ->and($log->description)->toBe('Menghapus Customer Budi')
        ->and($log->user_id)->toBe($sysadmin->id);
});

test('a model change made without a signed-in user records a log without actor or ip', function () {
    Category::create(['name' => 'Keyboard']);

    $log = ActivityLog::query()->sole();

    expect($log->module)->toBe('category')
        ->and($log->description)->toBe('Membuat Kategori Keyboard')
        ->and($log->user_id)->toBeNull()
        ->and($log->ip)->toBeNull();
});

test('an update that only touches timestamps records nothing', function () {
    $brand = Brand::create(['name' => 'Asus']);
    $before = ActivityLog::count();

    $brand->touch();

    expect(ActivityLog::count())->toBe($before);
});

test('creating and updating a stock item records one entry per save', function () {
    $laptop = Laptop::create([
        'brand_id' => Brand::create(['name' => 'Asus'])->id,
        'name' => 'ROG Strix G16',
        'warranty' => '1 tahun',
        'stock' => 1,
        'status' => 'SIAP JUAL',
        'buy_price' => 10000000,
        'sell_price' => 12000000,
    ]);

    $laptop->update(['stock' => 2]);

    $entries = ActivityLog::query()->where('module', 'laptop')->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('action')->all())->toBe([
            ActivityLog::ACTION_CREATED,
            ActivityLog::ACTION_UPDATED,
        ]);
});

test('login and logout are recorded in the activity log', function () {
    $user = User::factory()->withRole('ADMIN')->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password'])
        ->assertRedirect();

    $this->post('/logout')->assertRedirect();

    $actions = ActivityLog::query()
        ->whereIn('action', [ActivityLog::ACTION_LOGIN, ActivityLog::ACTION_LOGOUT])
        ->orderBy('id')
        ->get();

    expect($actions)->toHaveCount(2)
        ->and($actions[0]->description)->toBe('Login: '.$user->name)
        ->and($actions[0]->user_id)->toBe($user->id)
        ->and($actions[1]->description)->toBe('Logout: '.$user->name);
});

test('failed login does not write a log entry', function () {
    $user = User::factory()->withRole('ADMIN')->create();
    $before = ActivityLog::query()
        ->whereIn('action', [ActivityLog::ACTION_LOGIN, ActivityLog::ACTION_LOGOUT])
        ->count();

    $this->post('/login', ['username' => $user->username, 'password' => 'salah'])
        ->assertSessionHasErrors('username');

    expect(ActivityLog::query()
        ->whereIn('action', [ActivityLog::ACTION_LOGIN, ActivityLog::ACTION_LOGOUT])
        ->count())->toBe($before);
});

test('sysadmin can open the activity log page', function () {
    $sysadmin = User::factory()->withRole('SYSADMIN')->create();
    $this->actingAs($sysadmin);
    Brand::create(['name' => 'Asus']);

    $this->get('/activity-log')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ActivityLog/Index')
            ->has('logs.data', 2)
            ->where('logs.data.0.description', 'Membuat Brand Asus')
            ->where('logs.data.0.user_name', $sysadmin->name)
            ->where('logs.data.0.action', ActivityLog::ACTION_CREATED)
            ->where('logs.data.0.module', 'brand')
        );
});

test('activity log page filters by module and action', function () {
    $sysadmin = User::factory()->withRole('SYSADMIN')->create();
    $this->actingAs($sysadmin);
    Brand::create(['name' => 'Asus']);
    Category::create(['name' => 'Keyboard']);

    $this->get('/activity-log?module=category&action=created')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.module', 'category')
            ->where('logs.data.0.description', 'Membuat Kategori Keyboard')
        );
});

test('activity log page search escapes wildcard characters', function () {
    $sysadmin = User::factory()->withRole('SYSADMIN')->create();
    $this->actingAs($sysadmin);
    Brand::create(['name' => 'Asus']);

    $this->get('/activity-log?search=%25')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', 0));
});

test('user without the activity log permission cannot open the page', function () {
    $tech = User::factory()->withRole('TECH')->create();

    $this->actingAs($tech)->get('/activity-log')->assertForbidden();
});
