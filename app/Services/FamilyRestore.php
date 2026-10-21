<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Restores a FamilyBackup archive as a NEW family — it never overwrites
 * existing data, so a restore can be tried safely and compared side by side.
 *
 * All ids are remapped. People are matched by email; anyone missing gets a new
 * account with a random password and signs in via "Забыли пароль?".
 */
class FamilyRestore
{
    /** @var array<string, array<int, int>> table => old id => new id */
    private array $map = [];

    /** @var list<string> */
    public array $createdUsers = [];

    public function restore(string $zipPath, ?string $name = null): Family
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Cannot open {$zipPath}");
        }

        $dump = json_decode($zip->getFromName('data.json') ?: '', true);
        if (($dump['format'] ?? null) !== 1) {
            throw new RuntimeException('Not a Family Hub backup (data.json format 1 expected).');
        }
        $t = fn (string $table) => collect($dump['tables'][$table] ?? []);

        $storedFiles = [];
        try {
            $family = DB::transaction(function () use ($t, $name, $zip, &$storedFiles) {
                $old = $t('families')->first();
                $family = Family::create([
                    'name' => $name ?: ($old['name'] ?? 'Семья').' (восстановлено)',
                    'backup_frequency' => $old['backup_frequency'] ?? 'weekly',
                    'backup_keep' => $old['backup_keep'] ?? 8,
                    'alert_regions' => isset($old['alert_regions']) ? json_decode($old['alert_regions'], true) : null,
                ]);

                $this->restoreUsers($t('users'));
                foreach ($t('family_members') as $m) {
                    DB::table('family_members')->insertOrIgnore([
                        'family_id' => $family->id,
                        'user_id' => $this->id('users', $m['user_id']),
                        'role' => $m['role'] ?? Role::Member->value,
                        'relation' => $m['relation'] ?? null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                User::whereIn('id', $this->map['users'] ?? [])->whereNull('current_family_id')->update(['current_family_id' => $family->id]);
                $family->update(['created_by' => $this->id('users', $old['created_by'] ?? null)]);

                $f = ['family_id' => fn () => $family->id];
                $u = fn (string $col) => [$col => fn ($row) => $this->id('users', $row[$col])];

                $this->copy('task_categories', $t('task_categories'), $f);
                $this->copy('tasks', $t('tasks'), $f + ['category_id' => fn ($r) => $this->id('task_categories', $r['category_id'])] + $u('created_by') + $u('assignee_id'));
                $this->copy('task_checklist_items', $t('task_checklist_items'), ['task_id' => fn ($r) => $this->id('tasks', $r['task_id'])]);
                $this->copy('task_links', $t('task_links'), ['task_id' => fn ($r) => $this->id('tasks', $r['task_id'])]);
                $this->copy('maintenance_items', $t('maintenance_items'), $f + $u('assignee_id'));
                $this->copy('maintenance_logs', $t('maintenance_logs'), ['maintenance_item_id' => fn ($r) => $this->id('maintenance_items', $r['maintenance_item_id'])] + $u('done_by'));
                $this->copy('trips', $t('trips'), $f + $u('created_by'));
                foreach ($t('trip_user') as $row) {
                    DB::table('trip_user')->insertOrIgnore(['trip_id' => $this->id('trips', $row['trip_id']), 'user_id' => $this->id('users', $row['user_id'])]);
                }
                $this->copy('checkins', $t('checkins'), $f + $u('user_id') + ['trip_id' => fn ($r) => $this->id('trips', $r['trip_id'])]);
                $this->copy('travel_documents', $t('travel_documents'), $f + $u('traveller_id') + $u('uploaded_by') + ['trip_id' => fn ($r) => $this->id('trips', $r['trip_id'])]);
                $this->copy('threads', $t('threads'), $f + $u('created_by')
                    + ['task_id' => fn ($r) => $this->id('tasks', $r['task_id']), 'trip_id' => fn ($r) => $this->id('trips', $r['trip_id'])]);
                $this->copy('messages', $t('messages'), ['thread_id' => fn ($r) => $this->id('threads', $r['thread_id'])] + $u('user_id'));
                $this->copy('shopping_items', $t('shopping_items'), $f + $u('added_by') + $u('done_by'));

                $this->restoreAttachments($t('attachments'), $family, $zip, $storedFiles);

                return $family;
            });
        } catch (\Throwable $e) {
            foreach ($storedFiles as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        } finally {
            $zip->close();
        }

        return $family;
    }

    private function restoreUsers($users): void
    {
        foreach ($users as $u) {
            $user = User::where('email', $u['email'])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $u['name'], 'email' => $u['email'],
                    'password' => Str::random(40), 'avatar_color' => $u['avatar_color'] ?? '#475569',
                ]);
                $this->createdUsers[] = $u['email'];
            }
            $this->map['users'][$u['id']] = $user->id;
        }
    }

    /** Inserts rows with fresh ids, rewriting the given foreign keys, and remembers the id mapping. */
    private function copy(string $table, $rows, array $rewrite): void
    {
        foreach ($rows as $row) {
            $oldId = $row['id'] ?? null;
            unset($row['id']);
            foreach ($rewrite as $column => $fn) {
                $row[$column] = $fn($row);
            }
            $newId = DB::table($table)->insertGetId($row);
            if ($oldId !== null) {
                $this->map[$table][$oldId] = $newId;
            }
        }
    }

    private function restoreAttachments($rows, Family $family, ZipArchive $zip, array &$storedFiles): void
    {
        $owners = ['App\\Models\\Task' => 'tasks', 'App\\Models\\TravelDocument' => 'travel_documents', 'App\\Models\\Message' => 'messages'];
        $disk = config('familyhub.files_disk');

        foreach ($rows as $row) {
            $ownerTable = $owners[$row['attachable_type']] ?? null;
            $ownerId = $ownerTable ? $this->id($ownerTable, $row['attachable_id']) : null;
            if (! $ownerId) {
                continue;
            }

            $paths = [];
            foreach (['path', 'thumb_path'] as $col) {
                if (empty($row[$col])) {
                    $paths[$col] = null;

                    continue;
                }
                $contents = $zip->getFromName('files/'.$row[$col]);
                if ($contents === false) {
                    $paths[$col] = null;

                    continue;
                }
                $new = preg_replace('#^families/\d+/#', "families/{$family->id}/", $row[$col]);
                Storage::disk($disk)->put($new, $contents);
                $storedFiles[] = [$disk, $new];
                $paths[$col] = $new;
            }
            if (! $paths['path']) {
                continue; // file missing from the archive: skip the record
            }

            unset($row['id']);
            DB::table('attachments')->insert([
                'family_id' => $family->id,
                'attachable_id' => $ownerId,
                'uploaded_by' => $this->id('users', $row['uploaded_by']),
                'disk' => $disk,
            ] + $paths + $row);
        }
    }

    private function id(string $table, $oldId): ?int
    {
        return $oldId === null ? null : ($this->map[$table][$oldId] ?? null);
    }
}
