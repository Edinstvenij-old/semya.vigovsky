<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['family_id', 'name', 'icon', 'color', 'position'])]
class TaskCategory extends Model
{
    use BelongsToFamily;

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'category_id');
    }
}
