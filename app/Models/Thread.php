<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A topic thread: one purchase, one visit, one trip — instead of a single endless chat. */
#[Fillable(['family_id', 'created_by', 'task_id', 'trip_id', 'title', 'icon', 'is_pinned', 'last_message_at'])]
class Thread extends Model
{
    use BelongsToFamily;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
