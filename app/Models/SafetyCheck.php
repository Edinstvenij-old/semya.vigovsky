<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['family_id', 'created_by', 'note', 'closed_at'])]
class SafetyCheck extends Model
{
    use BelongsToFamily;

    /** A roll call stays open until closed by hand or for this many hours. */
    public const OPEN_HOURS = 24;

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SafetyResponse::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at')->where('created_at', '>=', now()->subHours(self::OPEN_HOURS));
    }
}
