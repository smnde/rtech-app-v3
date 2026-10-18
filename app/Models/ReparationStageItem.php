<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reparation_stage_id', 'label', 'cost', 'est_days'])]
class ReparationStageItem extends Model
{
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
    }

    public function stage()
    {
        return $this->belongsTo(ReparationStage::class, 'reparation_stage_id');
    }
}
