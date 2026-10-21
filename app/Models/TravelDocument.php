<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Boarding pass, bus ticket or booking. Protected by 2FA. */
#[Fillable(['family_id', 'trip_id', 'traveller_id', 'uploaded_by', 'type', 'title', 'carrier', 'reference', 'booking_code', 'from_place', 'to_place', 'departs_at', 'arrives_at', 'notes'])]
class TravelDocument extends Model
{
    use BelongsToFamily;

    public const TYPES = ['flight', 'bus', 'train', 'hotel', 'other'];

    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function traveller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveller_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
