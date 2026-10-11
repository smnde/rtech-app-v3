<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait GeneratesSku
{
    public static function bootGeneratesSku(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->sku)) {
                $model->sku = static::nextSku($model->skuPrefix());
            }
        });
    }

    abstract public function skuPrefix(): string;

    protected static function nextSku(string $prefix): string
    {
        $suffixLength = 4;
        $last = static::query()
            ->where('sku', 'like', $prefix.'-%')
            ->whereRaw('LENGTH(sku) = ?', [strlen($prefix) + 1 + $suffixLength])
            ->orderByDesc('sku')
            ->value('sku');

        $number = $last ? (int) substr($last, -$suffixLength) : 0;

        return sprintf('%s-%0'.$suffixLength.'d', $prefix, $number + 1);
    }
}
