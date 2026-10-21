<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['family_id', 'created_by', 'token_hash', 'role', 'note', 'max_uses', 'uses', 'expires_at', 'revoked_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use BelongsToFamily;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Creates an invitation and returns it together with the raw token (shown only once). */
    public static function issue(array $attributes): array
    {
        $token = Str::random(48);
        $invitation = static::create($attributes + ['token_hash' => hash('sha256', $token)]);

        return [$invitation, $token];
    }

    public static function findValidByToken(string $token): ?self
    {
        $invitation = static::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->first();

        return $invitation?->isValid() ? $invitation : null;
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->uses < $this->max_uses;
    }
}
