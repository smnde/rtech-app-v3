<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

test('sysadmin can create a custom role', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();

    $this->actingAs($sys)->post('/roles', ['name' => 'KASIR'])->assertSessionHas('success');

    expect(Role::where('name', 'KASIR')->exists())->toBeTrue();
});

test('role name must be unique and uppercase', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();

    $this->actingAs($sys)->post('/roles', ['name' => 'ADMIN'])->assertSessionHasErrors('name');
    $this->actingAs($sys)->post('/roles', ['name' => 'kasir'])->assertSessionHasErrors('name');
});

test('built-in role cannot be deleted', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();

    $this->actingAs($sys)->delete('/roles/'.Role::findByName('ADMIN')->id)
        ->assertSessionHas('error');

    expect(Role::where('name', 'ADMIN')->exists())->toBeTrue();
});

test('custom role in use cannot be deleted', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $custom = Role::create(['name' => 'KASIR', 'guard_name' => 'web']);
    User::factory()->withRole('KASIR')->create();

    $this->actingAs($sys)->delete("/roles/{$custom->id}")->assertSessionHas('error');

    expect(Role::where('name', 'KASIR')->exists())->toBeTrue();
});

test('unused custom role can be deleted', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $custom = Role::create(['name' => 'KASIR', 'guard_name' => 'web']);

    $this->actingAs($sys)->delete("/roles/{$custom->id}")->assertSessionHas('success');

    expect(Role::where('name', 'KASIR')->exists())->toBeFalse();
});

test('sysadmin can add a permission module with four actions', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();

    $this->actingAs($sys)->post('/permissions', ['module' => 'report_export'])->assertSessionHas('success');

    foreach (['show', 'add', 'edit', 'delete'] as $action) {
        expect(Permission::where('name', "{$action} report_export")->exists())->toBeTrue();
    }
});

test('new permission module is granted to owner and sysadmin', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();

    $this->actingAs($sys)->post('/permissions', ['module' => 'report_export']);

    expect(Role::findByName('OWNER')->hasPermissionTo('show report_export'))->toBeTrue();
    expect(Role::findByName('SYSADMIN')->hasPermissionTo('delete report_export'))->toBeTrue();
});

test('duplicate permission module is rejected', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();

    $this->actingAs($sys)->post('/permissions', ['module' => 'customer'])->assertSessionHasErrors('module');
});

test('admin cannot manage roles or permissions', function () {
    $admin = User::factory()->withRole('ADMIN')->create();

    $this->actingAs($admin)->get('/roles')->assertForbidden();
    $this->actingAs($admin)->get('/permissions')->assertForbidden();
    $this->actingAs($admin)->post('/roles', ['name' => 'KASIR'])->assertForbidden();
    $this->actingAs($admin)->post('/permissions', ['module' => 'report_export'])->assertForbidden();
});
