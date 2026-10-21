<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A recurring household duty: replace a filter, pay the electricity bill
 * (day/night tariff readings are stored in the log), check the boiler...
 */
#[Fillable(['family_id', 'assignee_id', 'title', 'description', 'kind', 'interval_days', 'next_due_on', 'remind_days_before', 'reminded_for'])]
class MaintenanceItem extends Model
{
    use BelongsToFamily;

    public const KINDS = ['consumable', 'bill', 'check', 'other'];

    protected function casts(): array
    {
        return [
            'next_due_on' => 'date:Y-m-d',
            'reminded_for' => 'date:Y-m-d',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->latest('done_on')->latest('id');
    }

    /** Records completion and moves the due date one interval forward from the completion date. */
    public function complete(User $user, array $data): MaintenanceLog
    {
        $doneOn = Carbon::parse($data['done_on'] ?? today());

        $log = $this->logs()->create($data + ['done_by' => $user->id, 'done_on' => $doneOn]);

        $this->update([
            'next_due_on' => $doneOn->copy()->addDays($this->interval_days),
            'reminded_for' => null,
        ]);

        return $log;
    }
}
