<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['family_id', 'category_id', 'created_by', 'assignee_id', 'title', 'description', 'status', 'priority', 'due_date', 'position', 'completed_at'])]
class Task extends Model
{
    use BelongsToFamily;

    public const STATUSES = ['todo', 'in_progress', 'done'];

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(TaskLink::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function thread(): HasOne
    {
        return $this->hasOne(Thread::class);
    }
}
