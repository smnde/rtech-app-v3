<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function isSysadmin(): bool
    {
        return $this->hasRole('SYSADMIN');
    }

    public function canTouchUser(self $target): bool
    {
        return $this->isSysadmin() || ! $target->isSysadmin();
    }

    public function canEditUser(self $target): bool
    {
        if ($this->is($target)) {
            return true;
        }

        return $this->can('edit user') && $this->canTouchUser($target);
    }

    public function canDeleteUser(self $target): bool
    {
        return $this->can('delete user') && $this->canTouchUser($target);
    }

    public function canAssignRole(string $role): bool
    {
        if ($role === 'SYSADMIN') {
            return $this->isSysadmin();
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function reparations()
    {
        return $this->hasMany(Reparation::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
