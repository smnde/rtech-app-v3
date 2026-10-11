<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class Permissions
{
    public const MODULES = [
        'user',
        'customer',
        'supplier',
        'reparation',
        'purchase',
        'sale',
        'report',
        'brand',
        'category',
        'laptop',
        'gadget',
        'aksesoris',
        'sparepart',
        'expense',
        'stock_movement',
    ];

    public const ACTIONS = ['show', 'add', 'edit', 'delete'];

    public const ROLES = ['OWNER', 'SYSADMIN', 'ADMIN', 'TECH'];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = "{$action} {$module}";
            }
        }

        return $permissions;
    }

    /**
     * Role bawaan dulu (urutan ROLES), lalu role buatan admin.
     *
     * @return list<string>
     */
    public static function roleNames(): array
    {
        $customRoleNames = Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', self::ROLES)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [...self::ROLES, ...$customRoleNames];
    }

    /**
     * Modul yang ada di database, urutan seed dipertahankan lalu modul baru.
     *
     * @return list<string>
     */
    public static function moduleNames(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->pluck('name')
            ->map(fn (string $name) => Str::after($name, ' '))
            ->unique()
            ->values()
            ->all();
    }
}
