<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'current_family_id', 'avatar_color', 'preferences'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'telegram_chat_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const DEFAULT_PREFERENCES = [
        'font_scale' => 1.25,       // larger than browser default — readable for every generation
        'high_contrast' => false,
        'simple_mode' => false,     // big tiles, read-only friendly; forced for viewers
        'push_logistics' => true,   // urgent, with sound
        'push_projects' => true,    // silent
        'push_maintenance' => true, // silent
        'push_chat' => true,        // silent
        'telegram_all' => false,    // Telegram: urgent only unless switched on
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_members')
            ->withPivot(['role', 'relation'])
            ->withTimestamps();
    }

    public function currentFamily(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'current_family_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function membership(?int $familyId = null): ?FamilyMember
    {
        $familyId ??= $this->current_family_id;

        return $familyId
            ? FamilyMember::where('family_id', $familyId)->where('user_id', $this->id)->first()
            : null;
    }

    public function allPreferences(): array
    {
        return array_merge(self::DEFAULT_PREFERENCES, $this->preferences ?? []);
    }

    public function pref(string $key): mixed
    {
        return $this->allPreferences()[$key] ?? null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** Shape returned to the SPA for the signed-in user. */
    public function toSessionArray(): array
    {
        $membership = $this->membership();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_color' => $this->avatar_color,
            'preferences' => $this->allPreferences(),
            'two_factor_enabled' => $this->hasTwoFactor(),
            'telegram_linked' => $this->telegram_chat_id !== null,
            'role' => $membership?->role?->value,
            'role_label' => $membership?->role?->label(),
            'can_write' => $membership?->role?->canWrite() ?? false,
            'is_admin' => $membership?->role === Role::Admin,
            'family' => $this->currentFamily?->only(['id', 'name']),
            'families' => $this->families->map(fn (Family $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'role' => $f->pivot->role,
            ])->values(),
        ];
    }
}
