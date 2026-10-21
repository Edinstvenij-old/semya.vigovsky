<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Realtime "something changed" signals: only members of that family may listen.
Broadcast::channel('family.{familyId}', function (User $user, int $familyId) {
    return $user->membership($familyId) !== null;
});
