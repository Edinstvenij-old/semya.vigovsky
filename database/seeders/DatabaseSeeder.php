<?php

namespace Database\Seeders;

use App\Enums\CheckinType;
use App\Enums\Role;
use App\Models\Checkin;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MaintenanceItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\Thread;
use App\Models\TravelDocument;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Vigovsky family, for local development: `php artisan migrate:fresh --seed`.
 * All demo accounts use the password "demo-password":
 *   papa@vigovsky.test, mama@vigovsky.test, nikolay@vigovsky.test (admins),
 *   yuriy@vigovsky.test, maria@vigovsky.test (members)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'demo-password';

        $member = fn (string $name, string $email, string $color) => User::create([
            'name' => $name, 'email' => $email, 'password' => $password, 'avatar_color' => $color,
        ]);

        $papa = $member('Николай Виговский (папа)', 'papa@vigovsky.test', '#334155');
        $mama = $member('Татьяна Виговская', 'mama@vigovsky.test', '#be185d');
        $nikolay = $member('Николай Виговский', 'nikolay@vigovsky.test', '#1d4ed8');
        $yuriy = $member('Юрий Виговский', 'yuriy@vigovsky.test', '#15803d');
        $maria = $member('Мария Виговская', 'maria@vigovsky.test', '#7e22ce');

        $family = Family::create(['name' => 'Виговские', 'created_by' => $nikolay->id]);
        foreach ([
            [$papa, Role::Admin, 'папа'],
            [$mama, Role::Admin, 'мама'],
            [$nikolay, Role::Admin, 'сын'],
            [$yuriy, Role::Member, 'сын'],
            [$maria, Role::Member, 'дочь'],
        ] as [$user, $role, $relation]) {
            FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => $role, 'relation' => $relation]);
            $user->update(['current_family_id' => $family->id]);
        }
        $family->seedDefaults();

        $cat = TaskCategory::where('family_id', $family->id)->pluck('id', 'name');

        $wiring = Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Установка оборудования'], 'created_by' => $papa->id,
            'assignee_id' => $nikolay->id, 'title' => 'Проводка и розетки в гараже', 'status' => 'in_progress',
            'priority' => 'high', 'due_date' => today()->addDays(5),
            'description' => 'Отдельная линия на верстак и зарядку для машины.',
        ]);
        foreach (['Кабель ВВГнг-LS 3×2.5 — 25 м', 'Автомат C16 ×2', 'УЗО 30 мА', 'Розетки IP44 ×4'] as $i => $text) {
            $wiring->checklist()->create(['text' => $text, 'position' => $i, 'is_done' => $i < 2]);
        }
        $wiring->links()->create(['url' => 'https://example.com/cable', 'title' => 'Кабель в строительном магазине', 'price' => 3250]);

        $ups = Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Ремонт техники'], 'created_by' => $yuriy->id,
            'assignee_id' => $yuriy->id, 'title' => 'Заменить аккумулятор в ИБП роутера', 'status' => 'todo',
        ]);
        $ups->checklist()->create(['text' => 'AGM 12V 9Ah, клеммы F2', 'position' => 0]);

        Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Покупки'], 'created_by' => $mama->id,
            'assignee_id' => $maria->id, 'title' => 'Подарок папе на день рождения', 'status' => 'todo', 'due_date' => today()->addDays(12),
        ]);
        Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Дом'], 'created_by' => $mama->id,
            'assignee_id' => $papa->id, 'title' => 'Повесить полки в прихожей', 'status' => 'done', 'completed_at' => now()->subDay(),
        ]);

        MaintenanceItem::create([
            'family_id' => $family->id, 'title' => 'Оплатить электроэнергию (день/ночь)', 'kind' => 'bill',
            'interval_days' => 30, 'next_due_on' => today()->addDays(2), 'assignee_id' => $mama->id,
        ]);
        MaintenanceItem::create([
            'family_id' => $family->id, 'title' => 'Заменить картриджи фильтра воды', 'kind' => 'consumable',
            'interval_days' => 90, 'next_due_on' => today()->subDays(3), 'assignee_id' => $papa->id,
        ]);
        MaintenanceItem::create([
            'family_id' => $family->id, 'title' => 'Проверить фильтр кондиционера', 'kind' => 'check',
            'interval_days' => 60, 'next_due_on' => today()->addDays(20),
        ]);

        $trip = Trip::create([
            'family_id' => $family->id, 'created_by' => $yuriy->id, 'title' => 'Юрий летит в Тбилиси',
            'destination' => 'Тбилиси', 'starts_at' => now()->subHours(5), 'ends_at' => now()->addDays(6),
        ]);
        $trip->travellers()->sync([$yuriy->id]);

        TravelDocument::create([
            'family_id' => $family->id, 'trip_id' => $trip->id, 'traveller_id' => $yuriy->id, 'uploaded_by' => $yuriy->id,
            'type' => 'flight', 'title' => 'Москва — Стамбул — Тбилиси', 'carrier' => 'Turkish Airlines', 'reference' => 'TK 414',
            'booking_code' => 'X7KQ2M', 'from_place' => 'Москва (VKO)', 'to_place' => 'Стамбул (IST)',
            'departs_at' => now()->subHours(3), 'arrives_at' => now()->addHour(),
        ]);

        foreach ([[CheckinType::ToAirport, 300], [CheckinType::BorderCrossed, 220], [CheckinType::Boarded, 190]] as [$type, $minutes]) {
            Checkin::create(['family_id' => $family->id, 'user_id' => $yuriy->id, 'trip_id' => $trip->id, 'type' => $type])
                ->forceFill(['created_at' => now()->subMinutes($minutes)])->save();
        }
        Checkin::create(['family_id' => $family->id, 'user_id' => $mama->id, 'type' => CheckinType::BackHome])
            ->forceFill(['created_at' => now()->subHours(2)])->save();

        $general = Thread::where('family_id', $family->id)->first();
        $weekend = Thread::create(['family_id' => $family->id, 'created_by' => $mama->id, 'title' => 'Семейный ужин в субботу', 'icon' => 'calendar-days']);
        foreach ([
            [$general, $nikolay, 'Всем привет! Теперь пишем сюда, а не в десять разных чатов.'],
            [$general, $papa, 'Хорошо, буду смотреть.'],
            [$weekend, $mama, 'В субботу ужинаем все вместе в 18:00. Кто что приносит?'],
            [$weekend, $maria, 'Я испеку пирог!'],
            [$weekend, $yuriy, 'Я в Тбилиси до пятницы, без меня.'],
        ] as [$thread, $user, $body]) {
            Message::create(['thread_id' => $thread->id, 'user_id' => $user->id, 'body' => $body]);
            $thread->update(['last_message_at' => now()]);
        }
    }
}
