<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mencatat perubahan model ke tabel `activity_logs` secara otomatis.
 *
 * Model yang memakai trait ini menghasilkan log `created`, `updated`, dan
 * `deleted` dengan deskripsi berbahasa Indonesia. Model yang punya ledger
 * sendiri (StockMovement, CashFlow) sengaja tidak memakainya supaya feed
 * tidak didominasi catatan turunan.
 */
trait LogsActivity
{
    /** Atribut yang nilainya tidak pernah ikut dicatat. */
    protected const ACTIVITY_IGNORED_ATTRIBUTES = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /** Maksimal perubahan atribut yang ditulis ke deskripsi. */
    protected const ACTIVITY_MAX_CHANGES = 5;

    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            $model->recordActivity('created');
        });

        static::updated(function (Model $model): void {
            $model->recordActivity('updated');
        });

        static::deleted(function (Model $model): void {
            $model->recordActivity('deleted');
        });
    }

    protected function recordActivity(string $action): void
    {
        $changes = $action === 'updated' ? $this->activityChanges() : [];

        // Update yang hanya menyentuh atribut terabaikan tidak perlu dicatat.
        if ($action === 'updated' && $changes === []) {
            return;
        }

        ActivityLog::record(
            action: $action,
            module: $this->activityModule(),
            description: $this->activityDescription($action, $changes),
            subject: $this,
        );
    }

    protected function activityModule(): string
    {
        return Str::snake(class_basename($this));
    }

    protected function activityTitle(): string
    {
        foreach (['name', 'invoice', 'title', 'code', 'username'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return "#{$this->getKey()}";
    }

    /**
     * @return array<string, string> Nama atribut => "nilai lama → nilai baru"
     */
    protected function activityChanges(): array
    {
        $ignored = [...self::ACTIVITY_IGNORED_ATTRIBUTES, ...$this->getHidden()];
        $changes = [];

        foreach ($this->getChanges() as $attribute => $after) {
            if (in_array($attribute, $ignored, true)) {
                continue;
            }

            $changes[$attribute] = $this->stringifyActivityValue($this->getOriginal($attribute))
                .' → '.$this->stringifyActivityValue($after);
        }

        return $changes;
    }

    /**
     * @param  array<string, string>  $changes
     */
    protected function activityDescription(string $action, array $changes): string
    {
        $module = $this->activityModule();
        $label = ActivityLog::MODULE_LABELS[$module] ?? Str::headline(class_basename($this));

        $verb = match ($action) {
            'created' => 'Membuat',
            'updated' => 'Mengubah',
            'deleted' => 'Menghapus',
            default => Str::headline($action),
        };

        $description = "{$verb} {$label} {$this->activityTitle()}";

        if ($changes === []) {
            return $description;
        }

        $shown = array_slice($changes, 0, self::ACTIVITY_MAX_CHANGES, true);
        $summary = collect($shown)
            ->map(fn (string $value, string $attribute) => "{$attribute}: {$value}")
            ->implode('; ');

        if (count($changes) > count($shown)) {
            $summary .= '; …';
        }

        return "{$description} — {$summary}";
    }

    protected function stringifyActivityValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'kosong',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_array($value) => '(data)',
            is_scalar($value) => Str::limit((string) $value, 60),
            default => '(data)',
        };
    }
}
