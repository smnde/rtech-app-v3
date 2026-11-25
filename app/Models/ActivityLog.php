<?php

namespace App\Models;

use App\Support\LikePattern;
use Database\Factories\ActivityLogFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'user_id',
    'action',
    'module',
    'description',
    'subject_type',
    'subject_id',
    'ip',
])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    /** Log sifatnya append-only: tidak pernah diubah setelah dicatat. */
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_LOGIN = 'login';

    public const ACTION_LOGOUT = 'logout';

    /** Label modul untuk deskripsi dan filter. */
    public const MODULE_LABELS = [
        'sale' => 'Penjualan',
        'purchase' => 'Pembelian',
        'reparation' => 'Servis',
        'cashflow' => 'Kas',
        'promo' => 'Promo',
        'customer' => 'Customer',
        'supplier' => 'Supplier',
        'brand' => 'Brand',
        'category' => 'Kategori',
        'laptop' => 'Laptop',
        'gadget' => 'Gadget',
        'accessory' => 'Aksesoris',
        'sparepart' => 'Sparepart',
        'user' => 'User',
        'activity_log' => 'Log Aktivitas',
    ];

    /**
     * Catat satu aktivitas. Aktor dan IP diambil dari request aktif kalau
     * tidak diberikan, supaya pemanggil tidak perlu mengulang kode yang sama.
     */
    public static function record(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?Authenticatable $user = null,
        ?string $ip = null,
    ): self {
        return self::create([
            'user_id' => ($user ?? Auth::user())?->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip' => $ip ?? self::currentIp(),
        ]);
    }

    private static function currentIp(): ?string
    {
        return app()->runningInConsole() ? null : request()->ip();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query
                ->where('description', 'like', LikePattern::contains($filters['search'])))
            ->when(filled($filters['module'] ?? null), fn (Builder $query) => $query
                ->where('module', $filters['module']))
            ->when(filled($filters['action'] ?? null), fn (Builder $query) => $query
                ->where('action', $filters['action']))
            ->when(filled($filters['user_id'] ?? null), fn (Builder $query) => $query
                ->where('user_id', $filters['user_id']))
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query
                ->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query
                ->where('created_at', '<=', $filters['to'].' 23:59:59'));
    }
}
