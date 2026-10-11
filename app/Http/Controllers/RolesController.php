<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
            ->get()
            ->sortBy(fn (Role $role) => array_search($role->name, Permissions::roleNames(), true))
            ->values()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'is_built_in' => in_array($role->name, Permissions::ROLES, true),
            ]);

        return inertia('Roles/Index', ['roles' => $roles]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
        ]);

        Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Role {$validated['name']} berhasil ditambahkan.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, Permissions::ROLES, true)) {
            return back()->with('error', 'Role bawaan sistem tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Role masih dipakai oleh user, pindahkan user terlebih dahulu.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Role {$role->name} berhasil dihapus.");
    }
}
