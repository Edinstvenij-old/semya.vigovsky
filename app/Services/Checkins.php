<?php

namespace App\Services;

use App\Enums\CheckinType;
use App\Models\Checkin;
use App\Models\User;
use App\Support\Live;

/** Records a status ("check-in") and tells the rest of the family with an urgent notification. */
class Checkins
{
    public function __construct(private Notifier $notifier) {}

    public function record(User $user, CheckinType $type, array $data = []): Checkin
    {
        $checkin = Checkin::create($data + ['user_id' => $user->id, 'type' => $type]);
        $checkin->load('user:id,name,avatar_color', 'trip:id,title');

        $details = array_filter([
            $type !== CheckinType::Custom ? $checkin->note : null,
            $checkin->trip?->title,
        ]);

        $this->notifier->send(
            $checkin->family_id,
            'logistics',
            "{$user->name}: {$checkin->label}",
            $details ? implode(' · ', $details) : null,
            in_array($type, [CheckinType::Safe, CheckinType::Shelter, CheckinType::NeedHelp], true) ? '/' : '/travel',
            $user,
        );

        Live::emit($checkin->family_id, 'checkin');

        return $checkin;
    }
}
