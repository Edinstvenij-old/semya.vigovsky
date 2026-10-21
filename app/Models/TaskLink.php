<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'url', 'title', 'price'])]
class TaskLink extends Model
{
    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
