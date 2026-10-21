<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['thread_id', 'user_id', 'body'])]
class Message extends Model
{
    protected static function booted(): void
    {
        static::deleting(fn (Message $m) => $m->attachments->each->delete());
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Messages have no own family_id column; Attachment::store() needs one for the file path and scope. */
    public function getFamilyIdAttribute(): ?int
    {
        return $this->thread?->family_id;
    }
}
