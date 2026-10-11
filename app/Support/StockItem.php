<?php

namespace App\Support;

use App\Models\Accessory;
use App\Models\Gadget;
use App\Models\Laptop;
use App\Models\Sparepart;
use Illuminate\Database\Eloquent\Model;

final class StockItem
{
    /**
     * @return array<string, class-string<Model>>
     */
    public static function types(): array
    {
        return [
            'laptop' => Laptop::class,
            'gadget' => Gadget::class,
            'accessory' => Accessory::class,
            'sparepart' => Sparepart::class,
        ];
    }

    public static function modelFor(string $type): string
    {
        return self::types()[$type];
    }
}
