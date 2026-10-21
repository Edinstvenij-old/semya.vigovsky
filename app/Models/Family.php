<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'created_by', 'backup_frequency', 'backup_keep', 'last_backup_at', 'alert_regions'])]
class Family extends Model
{
    protected function casts(): array
    {
        return [
            'last_backup_at' => 'datetime',
            'alert_regions' => 'array',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_members')
            ->withPivot(['role', 'relation'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TaskCategory::class)->orderBy('position');
    }

    /** Default categories and a general thread so a new family does not start with empty screens. */
    public function seedDefaults(): void
    {
        foreach ([
            ['Покупки', 'shopping-cart', '#15803d'],
            ['Ремонт техники', 'wrench', '#c2410c'],
            ['Установка оборудования', 'plug', '#1d4ed8'],
            ['Дом', 'home', '#7e22ce'],
        ] as $i => [$name, $icon, $color]) {
            TaskCategory::create(['family_id' => $this->id, 'position' => $i] + compact('name', 'icon', 'color'));
        }

        Thread::create([
            'family_id' => $this->id,
            'title' => 'Общий чат семьи',
            'icon' => 'users',
            'is_pinned' => true,
            'created_by' => $this->created_by,
        ]);
    }
}
