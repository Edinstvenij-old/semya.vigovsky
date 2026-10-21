<?php

namespace App\Enums;

/**
 * Quick check-in buttons. Logistics statuses are delivered to the rest
 * of the family as urgent notifications (with sound).
 */
enum CheckinType: string
{
    case LeftHome = 'left_home';
    case ToAirport = 'to_airport';
    case AtAirport = 'at_airport';
    case Boarded = 'boarded';
    case Landed = 'landed';
    case BorderCrossed = 'border_crossed';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case BackHome = 'back_home';
    case Delayed = 'delayed';
    case NeedHelp = 'need_help';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            // Gender-neutral wording: the same label reads correctly for every family member.
            self::LeftHome => 'Выхожу из дома',
            self::ToAirport => 'Еду в аэропорт',
            self::AtAirport => 'В аэропорту / на вокзале',
            self::Boarded => 'На борту / в автобусе',
            self::Landed => 'Самолёт приземлился',
            self::BorderCrossed => 'Граница пройдена',
            self::InTransit => 'В пункте пересадки',
            self::Arrived => 'На месте',
            self::BackHome => 'Уже дома',
            self::Delayed => 'Задержка',
            self::NeedHelp => 'Нужна помощь',
            self::Custom => 'Свой статус',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LeftHome => 'door-open',
            self::ToAirport => 'car',
            self::AtAirport => 'building-2',
            self::Boarded => 'plane-takeoff',
            self::Landed => 'plane-landing',
            self::BorderCrossed => 'shield-check',
            self::InTransit => 'refresh-cw',
            self::Arrived => 'map-pin',
            self::BackHome => 'home',
            self::Delayed => 'clock',
            self::NeedHelp => 'siren',
            self::Custom => 'message-square',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'icon' => $t->icon(),
        ], self::cases());
    }
}
