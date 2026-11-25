<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;

class ActivityLogsController extends Controller
{
    /** Label aksi untuk filter dan badge. */
    public const ACTION_LABELS = [
        ActivityLog::ACTION_CREATED => 'Dibuat',
        ActivityLog::ACTION_UPDATED => 'Diubah',
        ActivityLog::ACTION_DELETED => 'Dihapus',
        ActivityLog::ACTION_LOGIN => 'Login',
        ActivityLog::ACTION_LOGOUT => 'Logout',
    ];

    /** Warna badge per aksi. */
    private const ACTION_COLORS = [
        ActivityLog::ACTION_CREATED => 'green',
        ActivityLog::ACTION_UPDATED => 'yellow',
        ActivityLog::ACTION_DELETED => 'red',
        ActivityLog::ACTION_LOGIN => 'blue',
        ActivityLog::ACTION_LOGOUT => 'gray',
    ];

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'module' => $request->string('module')->toString() ?: null,
            'action' => $request->string('action')->toString() ?: null,
            'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->filter($filters)
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'user_name' => $log->user?->name ?? 'Sistem',
                'action' => $log->action,
                'module' => $log->module,
                'description' => $log->description,
                'ip' => $log->ip,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return inertia('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'modules' => collect(ActivityLog::MODULE_LABELS)
                ->map(fn (string $label, string $module) => ['id' => $module, 'label' => $label])
                ->values(),
            'actions' => collect(self::ACTION_LABELS)
                ->map(fn (string $label, string $action) => ['id' => $action, 'label' => $label, 'color' => self::ACTION_COLORS[$action] ?? 'gray'])
                ->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'label' => $user->name])
                ->values(),
        ]);
    }
}
