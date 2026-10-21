<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['safety_check_id', 'user_id', 'status', 'note'])]
class SafetyResponse extends Model
{
    public const STATUSES = ['safe', 'shelter', 'help'];

    public function check(): BelongsTo
    {
        return $this->belongsTo(SafetyCheck::class, 'safety_check_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
