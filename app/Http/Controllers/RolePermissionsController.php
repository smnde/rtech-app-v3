<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionsController extends Controller
{
    public function index(): Response
    {
        $roleNames = Permissions::roleNames();

        $roles = Role::query()
            ->with('permissions:id,name')
            ->where('guard_name', 'web')
            ->get()
            ->sortBy(fn (Role $role) => array_search($role->name, $roleNames, true))
            ->values()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ]);

        return inertia('SetPermission/Index', [
            'roles' => $roles,
            'modules' => Permissions::moduleNames(),
            'actions' => Permissions::ACTIONS,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        $role->syncPermissions($validated['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Permission role {$role->name} berhasil diperbarui.");
    }
}
