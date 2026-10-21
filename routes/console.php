<?php

use App\Enums\Role;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Invitation;
use App\Models\MaintenanceItem;
use App\Models\User;
use App\Services\FamilyBackup;
use App\Services\FamilyRestore;
use App\Services\Notifier;
use App\Services\Telegram;
use App\Support\Msg;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\VAPID;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('family:create {--family=} {--name=} {--email=}', function () {
    $familyName = $this->option('family') ?: text('Название семьи', required: true);
    $name = $this->option('name') ?: text('Ваше имя', required: true);
    $email = $this->option('email') ?: text('Email', required: true);
    $pass = password('Пароль (минимум 10 символов)', required: true, validate: fn ($v) => strlen($v) < 10 ? 'Слишком короткий' : null);

    DB::transaction(function () use ($familyName, $name, $email, $pass) {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $pass]);
        $family = Family::create(['name' => $familyName, 'created_by' => $user->id]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => Role::Admin]);
        $user->update(['current_family_id' => $family->id]);
        $family->seedDefaults();
    });

    $this->info("Семья «{$familyName}» создана, {$email} — администратор.");
})->purpose('Create a family and its first administrator');

Artisan::command('family:invite {family_id} {--role=member} {--hours=72}', function () {
    $family = Family::findOrFail($this->argument('family_id'));
    $admin = FamilyMember::where('family_id', $family->id)->where('role', Role::Admin)->firstOrFail();

    [, $token] = Invitation::issue([
        'family_id' => $family->id,
        'created_by' => $admin->user_id,
        'role' => Role::from($this->option('role')),
        'expires_at' => now()->addHours((int) $this->option('hours')),
    ]);

    $this->line(url("/invite/{$token}"));
})->purpose('Print a one-time invitation link');

Artisan::command('maintenance:remind', function (Notifier $notifier) {
    $sent = 0;
    MaintenanceItem::with('assignee')->get()->each(function (MaintenanceItem $item) use ($notifier, &$sent) {
        $remindFrom = $item->next_due_on->copy()->subDays($item->remind_days_before);
        $alreadyReminded = $item->reminded_for?->equalTo($item->next_due_on);

        if (today()->gte($remindFrom) && ! $alreadyReminded) {
            $when = $item->next_due_on->isPast() && ! $item->next_due_on->isToday()
                ? Msg::t('просрочено с :date', ['date' => $item->next_due_on->format('d.m')])
                : Msg::t('срок :date', ['date' => $item->next_due_on->format('d.m')]);

            $notifier->send(
                $item->family_id, 'maintenance', Msg::t('Пора: :title', ['title' => $item->title]), $when, '/home',
                recipients: $item->assignee ? [$item->assignee] : null,
            );
            $item->update(['reminded_for' => $item->next_due_on]);
            $sent++;
        }
    });

    $this->info("Reminders sent: {$sent}");
})->purpose('Send reminders for upcoming recurring household tasks');

Artisan::command('family:backup {--all : Back up every family regardless of schedule}', function (FamilyBackup $backup) {
    Family::all()->each(function (Family $family) use ($backup) {
        if ($this->option('all') || $backup->isDue($family)) {
            $this->line("{$family->name}: ".$backup->run($family));
        }
    });
})->purpose('Back up families whose backup is due');

Artisan::command('family:restore {file : Local .zip path, or a path on the backup disk such as backups/family-1/backup-….zip} {--name= : Name for the restored family}', function (FamilyRestore $restore) {
    $file = $this->argument('file');
    $tmp = null;
    if (! is_file($file)) {
        $disk = Storage::disk(config('familyhub.backup_disk'));
        if (! $disk->exists($file)) {
            $this->error("Backup not found: {$file}");

            return 1;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'fhr');
        file_put_contents($tmp, $disk->get($file));
        $file = $tmp;
    }

    try {
        $family = $restore->restore($file, $this->option('name'));
    } finally {
        if ($tmp) {
            @unlink($tmp);
        }
    }

    $this->info("Restored as a new family #{$family->id} «{$family->name}». Existing data was not touched.");
    if ($restore->createdUsers) {
        $this->warn('New accounts (use «Забыли пароль?» or an admin reset link): '.implode(', ', $restore->createdUsers));
    }
})->purpose('Restore a family backup archive as a new family');

Artisan::command('push:vapid', function () {
    try {
        $keys = VAPID::createVapidKeys();
    } catch (RuntimeException $e) {
        // Typical on Windows PHP builds: OpenSSL cannot find its config file.
        $this->error('OpenSSL could not create an EC key. On Windows set OPENSSL_CONF, e.g. C:\php\extras\ssl\openssl.cnf');

        return 1;
    }
    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
    $this->comment('Add these lines to .env');
})->purpose('Generate VAPID keys for Web Push');

Artisan::command('telegram:webhook', function (Telegram $telegram) {
    $secret = config('familyhub.telegram.webhook_secret');
    if (! $telegram->configured() || ! $secret) {
        $this->error('Set TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME and TELEGRAM_WEBHOOK_SECRET in .env first.');

        return 1;
    }

    $url = rtrim(config('app.url'), '/').'/api/telegram/webhook';
    $result = $telegram->setWebhook($url, $secret);
    $this->line(($result['ok'] ?? false) ? "Webhook set: {$url}" : 'Telegram error: '.($result['description'] ?? 'unknown'));
})->purpose('Register the Telegram webhook (needs a public https APP_URL)');

Schedule::command('maintenance:remind')->dailyAt('09:00');
Schedule::command('family:backup')->dailyAt('03:30');
