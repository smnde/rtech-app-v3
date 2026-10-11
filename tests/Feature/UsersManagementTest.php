<?php

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

test('admin can open users page', function () {
    $admin = User::factory()->withRole('ADMIN')->create();
    $this->actingAs($admin)->get('/users')->assertOk();
});

test('tech can only edit own account', function () {
    $tech = User::factory()->withRole('TECH')->create(['username' => 'tech1']);
    $other = User::factory()->withRole('ADMIN')->create(['username' => 'adm1']);

    $this->actingAs($tech)->put("/users/{$tech->id}", [
        'name' => 'Teknisi Sendiri', 'username' => 'tech1', 'role' => 'TECH',
    ])->assertRedirect();
    expect($tech->fresh()->name)->toBe('Teknisi Sendiri');

    $this->actingAs($tech)->put("/users/{$other->id}", [
        'name' => 'Diubah', 'username' => 'adm1', 'role' => 'ADMIN',
    ])->assertForbidden();
});

test('tech cannot create or delete users', function () {
    $tech = User::factory()->withRole('TECH')->create();
    $other = User::factory()->withRole('TECH')->create();

    $this->actingAs($tech)->post('/users', [
        'name' => 'Baru Dibuat', 'username' => 'baru1', 'role' => 'TECH',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertForbidden();

    $this->actingAs($tech)->delete("/users/{$other->id}")->assertForbidden();
    expect(User::find($other->id))->not->toBeNull();
});

test('user without edit permission cannot change own role', function () {
    $tech = User::factory()->withRole('TECH')->create(['username' => 'tek2']);
    $this->actingAs($tech)->put("/users/{$tech->id}", [
        'name' => 'Teknisi', 'username' => 'tek2', 'role' => 'ADMIN',
    ])->assertSessionHasErrors('role');
    expect($tech->fresh()->hasRole('TECH'))->toBeTrue();
});

test('owner can list users', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $this->actingAs($owner)->get('/users')->assertOk();
});

test('owner can create user with role', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $this->actingAs($owner)->post('/users', [
        'name' => 'Budi Kasir', 'username' => 'budi.k', 'role' => 'ADMIN',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertRedirect()->assertSessionHas('success');
    expect(User::where('username', 'budi.k')->first()->hasRole('ADMIN'))->toBeTrue();
});

test('duplicate username rejected', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    User::factory()->create(['username' => 'dup']);
    $this->actingAs($owner)->post('/users', [
        'name' => 'Lain', 'username' => 'dup', 'role' => 'TECH',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertSessionHasErrors('username');
});

test('owner cannot assign sysadmin role', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $this->actingAs($owner)->post('/users', [
        'name' => 'Calon Sysadmin', 'username' => 'sys2', 'role' => 'SYSADMIN',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertSessionHasErrors('role');
});

test('sysadmin can assign owner role', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $this->actingAs($sys)->post('/users', [
        'name' => 'Calon Owner', 'username' => 'own2', 'role' => 'OWNER',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertSessionHas('success');
    expect(User::where('username', 'own2')->first()->hasRole('OWNER'))->toBeTrue();
});

test('owner cannot edit or delete sysadmin account', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $sys = User::factory()->withRole('SYSADMIN')->create(['username' => 'sys3']);

    $this->actingAs($owner)->put("/users/{$sys->id}", [
        'name' => 'Diubah', 'username' => 'sys3', 'role' => 'SYSADMIN',
    ])->assertForbidden();
    $this->actingAs($owner)->delete("/users/{$sys->id}")->assertForbidden();
    expect(User::find($sys->id))->not->toBeNull();
});

test('sysadmin can edit owner account', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $owner = User::factory()->withRole('OWNER')->create(['username' => 'own3']);

    $this->actingAs($sys)->put("/users/{$owner->id}", [
        'name' => 'Owner Diubah', 'username' => 'own3', 'role' => 'OWNER',
    ])->assertRedirect()->assertSessionHas('success');
    expect($owner->fresh()->name)->toBe('Owner Diubah');
});

test('update without password keeps old password', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $target = User::factory()->withRole('TECH')->create(['username' => 'tek1']);
    $oldHash = $target->password;
    $this->actingAs($owner)->put("/users/{$target->id}", [
        'name' => 'Teknisi Baru', 'username' => 'tek1', 'role' => 'TECH',
    ])->assertRedirect();
    expect($target->fresh()->password)->toBe($oldHash);
});

test('user cannot delete own account', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $this->actingAs($owner)->delete("/users/{$owner->id}")->assertRedirect()->assertSessionHas('error');
    expect(User::find($owner->id))->not->toBeNull();
});

test('user with transactions cannot be deleted without 500', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $tech = User::factory()->withRole('TECH')->create();
    DB::table('sales')->insert([
        'user_id' => $tech->id, 'invoice' => 'INV-261011-01', 'subtotal' => 0, 'discount' => 0, 'total' => 0,
        'payment_status' => 'lunas', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($owner)->delete("/users/{$tech->id}")
        ->assertRedirect()
        ->assertSessionHas('error');
    expect(User::find($tech->id))->not->toBeNull();
});

test('admin cannot change own role', function () {
    $admin = User::factory()->withRole('ADMIN')->create(['username' => 'adm4']);
    $this->actingAs($admin)->put("/users/{$admin->id}", [
        'name' => 'Admin', 'username' => 'adm4', 'role' => 'TECH',
    ])->assertSessionHasErrors('role');
    expect($admin->fresh()->hasRole('ADMIN'))->toBeTrue();
});

test('owner cannot change another user role', function () {
    $owner = User::factory()->withRole('OWNER')->create();
    $tech = User::factory()->withRole('TECH')->create(['username' => 'tek4']);
    $this->actingAs($owner)->put("/users/{$tech->id}", [
        'name' => 'Teknisi', 'username' => 'tek4', 'role' => 'ADMIN',
    ])->assertSessionHasErrors('role');
    expect($tech->fresh()->hasRole('TECH'))->toBeTrue();
});

test('owner cannot change own role', function () {
    $owner = User::factory()->withRole('OWNER')->create(['username' => 'own4']);
    $this->actingAs($owner)->put("/users/{$owner->id}", [
        'name' => 'Owner', 'username' => 'own4', 'role' => 'ADMIN',
    ])->assertSessionHasErrors('role');
    expect($owner->fresh()->hasRole('OWNER'))->toBeTrue();
});

test('sysadmin can change other user role', function () {
    $sys = User::factory()->withRole('SYSADMIN')->create();
    $tech = User::factory()->withRole('TECH')->create(['username' => 'tek5']);
    $this->actingAs($sys)->put("/users/{$tech->id}", [
        'name' => 'Teknisi', 'username' => 'tek5', 'role' => 'ADMIN',
    ])->assertRedirect()->assertSessionHas('success');
    expect($tech->fresh()->hasRole('ADMIN'))->toBeTrue();
});
