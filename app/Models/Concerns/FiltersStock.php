<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersStock
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'sku'];
    }

    /**
     * @return list<string>
     */
    protected function filterableColumns(): array
    {
        return ['brand_id', 'supplier_id', 'status'];
    }

    public function scopeFilterFromRequest(Builder $query, Request $request): Builder
    {
        $query->when($request->search, function (Builder $query, string $search) {
            $query->where(function (Builder $query) use ($search) {
                foreach ($this->searchableColumns() as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        });

        foreach ($this->filterableColumns() as $column) {
            $query->when($request->input($column), fn (Builder $query, $value) => $query->where($column, $value));
        }

        return $query->orderBy('sku', $request->input('sort') === 'desc' ? 'desc' : 'asc');
    }

    public static function sortFromRequest(Request $request): string
    {
        return $request->input('sort') === 'desc' ? 'desc' : 'asc';
    }
}
