<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aksi destruktif (hapus) untuk role SYSADMIN wajib menyertakan password akun.
 * User lain lewat begitu saja.
 */
class RequireSysadminPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSysadmin()) {
            $password = (string) $request->input('password', '');

            if ($password === '' || ! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Password salah atau belum diisi.',
                ]);
            }
        }

        return $next($request);
    }
}
