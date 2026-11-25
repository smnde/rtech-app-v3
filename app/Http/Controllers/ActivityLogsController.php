<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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
            'purgeableMonths' => $this->purgeableMonths(),
            'currentMonthLabel' => self::monthLabel(now()),
        ]);
    }

    /**
     * Hapus seluruh log pada satu bulan. Hanya bulan yang sudah lewat yang
     * boleh dihapus: log November baru bisa dihapus mulai 1 Desember.
     */
    public function destroyMonth(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        if (! self::isPurgeable($month)) {
            throw ValidationException::withMessages([
                'month' => 'Log bulan berjalan belum bisa dihapus. Tunggu sampai bulan berikutnya.',
            ]);
        }

        $deleted = ActivityLog::query()
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->delete();

        return back()->with(
            'success',
            'Log '.self::monthLabel($month)." dihapus ({$deleted} entri).",
        );
    }

    /**
     * Bulan yang sudah selesai dan masih punya log, terbaru lebih dulu.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    private function purgeableMonths(): array
    {
        $oldest = ActivityLog::query()->min('created_at');

        if ($oldest === null) {
            return [];
        }

        $months = [];
        $cursor = Carbon::parse($oldest)->startOfMonth();
        $limit = now()->startOfMonth();

        while ($cursor->lt($limit)) {
            $count = ActivityLog::query()
                ->whereBetween('created_at', [$cursor->copy(), $cursor->copy()->endOfMonth()])
                ->count();

            if ($count > 0) {
                $months[] = [
                    'value' => $cursor->format('Y-m'),
                    'label' => self::monthLabel($cursor),
                    'count' => $count,
                ];
            }

            $cursor->addMonthNoOverflow();
        }

        return array_reverse($months);
    }

    /**
     * Boleh dihapus kalau bulannya sudah berakhir sebelum bulan berjalan.
     */
    public static function isPurgeable(Carbon $month): bool
    {
        return $month->copy()->endOfMonth()->lt(now()->startOfMonth());
    }

    /**
     * Label bulan berbahasa Indonesia (locale aplikasi masih `en`).
     */
    public static function monthLabel(Carbon $month): string
    {
        $names = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $names[(int) $month->month].' '.$month->year;
    }
}
