<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['family_id', 'user_id', 'channel', 'urgent', 'title', 'body', 'url', 'read_at'])]
class FamilyNotification extends Model
{
    use BelongsToFamily;

    public const CHANNELS = ['logistics', 'projects', 'maintenance', 'chat', 'family'];

    protected function casts(): array
    {
        return [
            'urgent' => 'boolean',
            'read_at' => 'datetime',
        ];
    }
}
