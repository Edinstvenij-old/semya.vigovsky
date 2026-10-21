<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Family;
use App\Models\FamilyMember;

/**
 * Holds the family of the current request. Set by the SetFamilyContext
 * middleware and read by the BelongsToFamily global scope, so every
 * family-owned model is automatically isolated per family.
 */
class FamilyContext
{
    private ?Family $family = null;

    private ?FamilyMember $membership = null;

    public function set(Family $family, FamilyMember $membership): void
    {
        $this->family = $family;
        $this->membership = $membership;
    }

    public function clear(): void
    {
        $this->family = null;
        $this->membership = null;
    }

    public function family(): ?Family
    {
        return $this->family;
    }

    public function id(): ?int
    {
        return $this->family?->id;
    }

    public function role(): ?Role
    {
        return $this->membership?->role;
    }

    public function isAdmin(): bool
    {
        return $this->role() === Role::Admin;
    }
}
