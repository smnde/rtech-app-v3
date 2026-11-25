<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]

class Category extends Model
{
    use LogsActivity;

    public function spareparts()
    {
        return $this->hasMany(Sparepart::class);
    }
}
