<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('any user can open own profile page', function () {
    $tech = User::factory()->create();
    $this->actingAs($tech)->get('/profile')->assertOk();
});

test('user can change own name', function () {
    $tech = User::factory()->create(['name' => 'Nama Lama']);
    $this->actingAs($tech)->patch('/profile', ['name' => 'Nama Baru'])
        ->assertRedirect('/profile')
        ->assertSessionHas('success');
    expect($tech->fresh()->name)->toBe('Nama Baru');
});

test('profile name is required and at least 3 characters', function () {
    $tech = User::factory()->create(['name' => 'Nama Lama']);
    $this->actingAs($tech)->patch('/profile', ['name' => 'ab'])->assertSessionHasErrors('name');
    expect($tech->fresh()->name)->toBe('Nama Lama');
});

test('user can change own password with current password', function () {
    $tech = User::factory()->create();
    $this->actingAs($tech)->from('/profile')->put('/password', [
        'current_password' => 'password',
        'password' => 'password-baru',
        'password_confirmation' => 'password-baru',
    ])->assertSessionHas('success');
    expect(Hash::check('password-baru', $tech->fresh()->password))->toBeTrue();
});
