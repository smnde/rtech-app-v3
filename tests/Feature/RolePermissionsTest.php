<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

test('sysadmin can open role permission page', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $this->actingAs($sys)->get('/set-permission')->assertOk();
});

test('tech cannot open role permission page', function () {
    $tech = User::factory()->withRole('TECH')->create();
    $this->actingAs($tech)->get('/set-permission')->assertForbidden();
});

test('tech without permissions cannot open customers', function () {
    $tech = User::factory()->withRole('TECH')->create();
    $this->actingAs($tech)->get('/customers')->assertForbidden();
});

test('sysadmin can update permissions for a role', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $admin = Role::findByName('ADMIN');

    $this->actingAs($sys)->put("/set-permission/{$admin->id}", [
        'permissions' => ['show customer', 'add customer'],
    ])->assertSessionHas('success');

    expect($admin->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['add customer', 'show customer']);
});

test('updated role permission applies to users with that role', function () {
    $admin = User::factory()->withRole('ADMIN')->create();
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $this->actingAs($sys)->put('/set-permission/'.Role::findByName('ADMIN')->id, [
        'permissions' => ['show customer'],
    ]);

    $this->actingAs($admin->fresh())->get('/customers')->assertOk();
});

test('owner cannot open role permission page', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $this->actingAs($owner)->get('/set-permission')->assertForbidden();
});

test('admin cannot update role permissions', function () {
    $admin = User::factory()->withRole('ADMIN')->create();
    $this->actingAs($admin)->put('/set-permission/'.Role::findByName('TECH')->id, [
        'permissions' => ['show customer'],
    ])->assertForbidden();
    expect(Role::findByName('TECH')->hasPermissionTo('show customer'))->toBeFalse();
});

test('sysadmin can update permissions of a custom role', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $custom = Role::create(['name' => 'KASIR', 'guard_name' => 'web']);

    $this->actingAs($sys)->put("/set-permission/{$custom->id}", [
        'permissions' => ['show customer'],
    ])->assertSessionHas('success');

    expect($custom->fresh()->hasPermissionTo('show customer'))->toBeTrue();
});
