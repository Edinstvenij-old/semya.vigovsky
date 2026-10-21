<?php

namespace App\Models;

use App\Enums\CheckinType;
use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['family_id', 'user_id', 'trip_id', 'type', 'note', 'lat', 'lng'])]
#[Appends(['label', 'icon'])]
class Checkin extends Model
{
    use BelongsToFamily;

    protected function casts(): array
    {
        return [
            'type' => CheckinType::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->type === CheckinType::Custom && $this->note ? $this->note : $this->type->label();
    }

    public function getIconAttribute(): string
    {
        return $this->type->icon();
    }
}
