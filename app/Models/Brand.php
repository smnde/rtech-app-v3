<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]

class Brand extends Model
{
    public function laptops()
    {
        return $this->hasMany(Laptop::class);
    }

    public function gadgets()
    {
        return $this->hasMany(Gadget::class);
    }

    public function spareparts()
    {
        return $this->hasMany(Sparepart::class);
    }

    public function accessories()
    {
        return $this->hasMany(Accessory::class);
    }
}
