<?php

namespace App\Models\Concerns;

use App\Models\Family;
use App\Support\FamilyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the family of the current request and fills
 * family_id on create. Outside of a request (console, queue) no scope
 * is applied.
 */
trait BelongsToFamily
{
    public static function bootBelongsToFamily(): void
    {
        static::addGlobalScope('family', function (Builder $query) {
            if ($id = app(FamilyContext::class)->id()) {
                $query->where($query->getModel()->getTable().'.family_id', $id);
            }
        });

        static::creating(function ($model) {
            $model->family_id ??= app(FamilyContext::class)->id();
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
