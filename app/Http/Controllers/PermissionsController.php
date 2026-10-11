<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsController extends Controller
{
    public function index(): Response
    {
        return inertia('Permissions/Index', [
            'modules' => Permissions::moduleNames(),
            'actions' => Permissions::ACTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(Permissions::moduleNames()),
            ],
        ]);

        $module = $validated['module'];

        foreach (Permissions::ACTIONS as $action) {
            Permission::create(['name' => "{$action} {$module}", 'guard_name' => 'web']);
        }

        Role::query()
            ->whereIn('name', ['OWNER', 'SYSADMIN'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(
                array_map(fn (string $action) => "{$action} {$module}", Permissions::ACTIONS),
            ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Permission untuk modul {$module} berhasil ditambahkan.");
    }
}
