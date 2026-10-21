<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['family_id', 'created_by', 'title', 'destination', 'starts_at', 'ends_at'])]
class Trip extends Model
{
    use BelongsToFamily;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function travellers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TravelDocument::class)->orderBy('departs_at');
    }
}
