<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['maintenance_item_id', 'done_by', 'done_on', 'note', 'amount', 'reading_day', 'reading_night'])]
class MaintenanceLog extends Model
{
    protected function casts(): array
    {
        return [
            'done_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'reading_day' => 'decimal:2',
            'reading_night' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class, 'maintenance_item_id');
    }

    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
