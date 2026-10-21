<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Member => 'Участник',
            self::Viewer => 'Наблюдатель',
        };
    }
}
