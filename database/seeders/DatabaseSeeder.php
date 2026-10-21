<?php

namespace Database\Seeders;

use App\Enums\CheckinType;
use App\Enums\Role;
use App\Models\Checkin;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MaintenanceItem;
use App\Models\Message;
use App\Models\ShoppingItem;
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
        $wiring->links()->create(['url' => 'https://example.com/cable', 'title' => 'Кабель в строительном магазине', 'price' => 1450]);

        $ups = Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Ремонт техники'], 'created_by' => $yuriy->id,
            'assignee_id' => $yuriy->id, 'title' => 'Заменить аккумулятор в ИБП роутера', 'status' => 'todo',
        ]);
        $ups->checklist()->create(['text' => 'AGM 12V 9Ah, клеммы F2', 'position' => 0]);

        $blackout = Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Покупки'], 'created_by' => $papa->id,
            'assignee_id' => $nikolay->id, 'title' => 'Запас на отключения света', 'status' => 'todo', 'priority' => 'high',
            'due_date' => today()->addDays(3),
        ]);
        foreach (['Павербанк 20 000 мА·ч ×2', 'Налобные фонарики ×3', 'Батарейки AA ×12', 'Вода 6 л ×4'] as $i => $text) {
            $blackout->checklist()->create(['text' => $text, 'position' => $i, 'is_done' => $i === 0]);
        }

        Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Покупки'], 'created_by' => $mama->id,
            'assignee_id' => $maria->id, 'title' => 'Подарок папе на день рождения', 'status' => 'todo', 'due_date' => today()->addDays(12),
        ]);
        Task::create([
            'family_id' => $family->id, 'category_id' => $cat['Дом'], 'created_by' => $mama->id,
            'assignee_id' => $papa->id, 'title' => 'Повесить полки в прихожей', 'status' => 'done', 'completed_at' => now()->subDay(),
        ]);

        $power = MaintenanceItem::create([
            'family_id' => $family->id, 'title' => 'Оплатить электроэнергию (день/ночь)', 'kind' => 'bill',
            'interval_days' => 30, 'next_due_on' => today()->addDays(2), 'assignee_id' => $mama->id,
            'description' => 'Двухзонный учёт: T1 — день, T2 — ночь (23:00–7:00).',
        ]);
        $power->logs()->create([
            'done_by' => $mama->id, 'done_on' => today()->subDays(28), 'amount' => 1186.40,
            'reading_day' => 24518, 'reading_night' => 9731,
        ]);
        MaintenanceItem::create([
            'family_id' => $family->id, 'title' => 'Зарядить зарядную станцию и проверить генератор', 'kind' => 'check',
            'interval_days' => 14, 'next_due_on' => today()->addDay(), 'assignee_id' => $papa->id,
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
            'family_id' => $family->id, 'created_by' => $yuriy->id, 'title' => 'Юрий едет в Тбилиси через Кишинёв',
            'destination' => 'Тбилиси', 'starts_at' => now()->subHours(9), 'ends_at' => now()->addDays(6),
        ]);
        $trip->travellers()->sync([$yuriy->id]);

        // Typical route from Odesa: bus to Chișinău, then a flight from there.
        TravelDocument::create([
            'family_id' => $family->id, 'trip_id' => $trip->id, 'traveller_id' => $yuriy->id, 'uploaded_by' => $yuriy->id,
            'type' => 'bus', 'title' => 'Одесса — Кишинёв', 'carrier' => 'Междугородний автобус', 'reference' => 'Рейс 0715, место 14',
            'from_place' => 'Одесса, автовокзал', 'to_place' => 'Кишинёв, центральный автовокзал',
            'departs_at' => now()->subHours(9), 'arrives_at' => now()->subHours(3),
            'notes' => 'Граница: Маяки — Удобное. Паспорт и посадочный талон на самолёт держать под рукой.',
        ]);
        TravelDocument::create([
            'family_id' => $family->id, 'trip_id' => $trip->id, 'traveller_id' => $yuriy->id, 'uploaded_by' => $yuriy->id,
            'type' => 'flight', 'title' => 'Кишинёв — Тбилиси', 'carrier' => 'Авиакомпания', 'reference' => '5F 787',
            'booking_code' => 'X7KQ2M', 'from_place' => 'Кишинёв (KIV)', 'to_place' => 'Тбилиси (TBS)',
            'departs_at' => now()->addHour(), 'arrives_at' => now()->addHours(4),
        ]);

        foreach ([
            [CheckinType::LeftHome, 560, null],
            [CheckinType::BorderCrossed, 400, 'Маяки — Удобное'],
            [CheckinType::AtAirport, 90, 'Кишинёв, жду посадку'],
        ] as [$type, $minutes, $note]) {
            Checkin::create(['family_id' => $family->id, 'user_id' => $yuriy->id, 'trip_id' => $trip->id, 'type' => $type, 'note' => $note])
                ->forceFill(['created_at' => now()->subMinutes($minutes)])->save();
        }
        Checkin::create(['family_id' => $family->id, 'user_id' => $mama->id, 'type' => CheckinType::BackHome])
            ->forceFill(['created_at' => now()->subHours(2)])->save();

        foreach ([['Хлеб', null, $mama], ['Молоко', '2 л', $mama], ['Батарейки AA', '8 шт', $papa], ['Корм коту', null, $maria]] as [$text, $qty, $who]) {
            ShoppingItem::create(['family_id' => $family->id, 'added_by' => $who->id, 'text' => $text, 'quantity' => $qty]);
        }

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
