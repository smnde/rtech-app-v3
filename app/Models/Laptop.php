<?php

namespace App\Models;

use App\Models\Concerns\FiltersStock;
use App\Models\Concerns\GeneratesSku;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[Fillable(
    [
        'brand_id',
        'supplier_id',
        'name',
        'sku',
        'code',
        'image',
        'serialnumber',
        'warranty',
        'stock',
        'buy_price',
        'sell_price',
        'is_posted',
        'accessories',
        'features',
        'specification',
        'issue_notes',
        'notes',
        'status',
    ]
)]

class Laptop extends Model
{
    use FiltersStock, GeneratesSku;

    protected function searchableColumns(): array
    {
        return ['name', 'sku', 'code', 'serialnumber'];
    }

    protected $casts = [
        'accessories' => 'array',
        'features' => 'array',
        'specification' => 'array',
        'issue_notes' => 'array',
        'is_posted' => 'boolean',
    ];

    public function skuPrefix(): string
    {
        return 'LTP';
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function itemLogs(): MorphMany
    {
        return $this->morphMany(ItemLog::class, 'item')->latest();
    }

    /**
     * @param  list<string>  $texts
     * @param  list<array{id: string, text: string, solved: bool, solved_at: ?string}>  $existing
     * @return list<array{id: string, text: string, solved: bool, solved_at: ?string}>
     */
    public static function issuesFromTexts(array $texts, array $existing = []): array
    {
        $existingIssues = collect($existing);

        return collect($texts)
            ->map(fn (string $text) => $existingIssues->firstWhere('text', $text) ?? [
                'id' => (string) Str::uuid(),
                'text' => $text,
                'solved' => false,
                'solved_at' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, text: string, solved: bool, solved_at: ?string}>
     */
    public function issues(): array
    {
        return collect($this->issue_notes ?? [])
            ->map(fn (mixed $issue) => is_array($issue) ? $issue : [
                'id' => md5((string) $issue),
                'text' => (string) $issue,
                'solved' => false,
                'solved_at' => null,
            ])
            ->values()
            ->all();
    }
}
