<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['url', 'type', 'model_type', 'model_id', 'is_primary'])]
class Media extends Model
{
    use HasFactory;

    /**
     * Get the parent model (product, user, variant, etc.).
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
