<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $currentUser = $request->user();

        $users = User::query()
            ->with('roles')
            ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->getRoleNames()->first(),
                'created_at' => $user->created_at?->toDateString(),
                'can_edit' => $currentUser->canEditUser($user),
                'can_delete' => $currentUser->canDeleteUser($user),
            ]);

        return inertia('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $request->search],
            'canCreate' => $currentUser->can('add user'),
            'roles' => Permissions::roleNames(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'username', 'password']));
        $user->assignRole($request->validated('role'));

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'username', 'password']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $user->syncRoles([$request->validated('role')]);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->canDeleteUser($user), 403);

        if ($request->user()->is($user)) {
            return back()->with('error', 'Tidak dapat menghapus akun yang sedang digunakan.');
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->with('error', 'User tidak dapat dihapus karena sudah memiliki data transaksi.');
        }

        return back()->with('success', 'User berhasil dihapus.');
    }
}
