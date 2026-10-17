<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reparation_id', 'sequence', 'status', 'confirmed_at'])]
class ReparationStage extends Model
{
    public function reparation()
    {
        return $this->belongsTo(Reparation::class);
    }

    public function items()
    {
        return $this->hasMany(ReparationStageItem::class);
    }
}
