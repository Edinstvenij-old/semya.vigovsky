<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Family;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Exports everything that belongs to one family (data as JSON + all files)
 * into a zip archive on the backup disk and rotates old archives.
 */
class FamilyBackup
{
    /** Tables with a family_id column. */
    private const FAMILY_TABLES = [
        'families' => 'id', 'family_members' => 'family_id', 'task_categories' => 'family_id',
        'tasks' => 'family_id', 'attachments' => 'family_id', 'maintenance_items' => 'family_id',
        'trips' => 'family_id', 'checkins' => 'family_id', 'travel_documents' => 'family_id',
        'threads' => 'family_id', 'shopping_items' => 'family_id',
    ];

    public function directory(Family $family): string
    {
        return "backups/family-{$family->id}";
    }

    public function run(Family $family): string
    {
        $data = [];
        foreach (self::FAMILY_TABLES as $table => $column) {
            $data[$table] = DB::table($table)->where($column, $family->id)->get();
        }

        $taskIds = $data['tasks']->pluck('id');
        $threadIds = $data['threads']->pluck('id');
        $data['task_checklist_items'] = DB::table('task_checklist_items')->whereIn('task_id', $taskIds)->get();
        $data['task_links'] = DB::table('task_links')->whereIn('task_id', $taskIds)->get();
        $data['maintenance_logs'] = DB::table('maintenance_logs')
            ->whereIn('maintenance_item_id', $data['maintenance_items']->pluck('id'))->get();
        $data['trip_user'] = DB::table('trip_user')->whereIn('trip_id', $data['trips']->pluck('id'))->get();
        $data['messages'] = DB::table('messages')->whereIn('thread_id', $threadIds)->get();
        $data['users'] = DB::table('users')
            ->whereIn('id', $data['family_members']->pluck('user_id'))
            ->get(['id', 'name', 'email', 'avatar_color', 'created_at']);

        $tmp = tempnam(sys_get_temp_dir(), 'fhb');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create backup archive');
        }

        $zip->addFromString('data.json', json_encode([
            'format' => 1,
            'created_at' => now()->toIso8601String(),
            'family' => $family->name,
            'tables' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Attachment::withoutGlobalScopes()->where('family_id', $family->id)->each(function (Attachment $a) use ($zip) {
            $disk = Storage::disk($a->disk);
            foreach (array_filter([$a->path, $a->thumb_path]) as $path) {
                if ($disk->exists($path)) {
                    $zip->addFromString('files/'.$path, $disk->get($path));
                }
            }
        });

        $zip->close();

        $name = $this->directory($family).'/backup-'.now()->format('Y-m-d_His').'.zip';
        Storage::disk(config('familyhub.backup_disk'))->put($name, fopen($tmp, 'r'));
        @unlink($tmp);

        $family->update(['last_backup_at' => now()]);
        $this->rotate($family);

        return $name;
    }

    public function list(Family $family): array
    {
        $disk = Storage::disk(config('familyhub.backup_disk'));

        return collect($disk->files($this->directory($family)))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->sortDesc()
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => $disk->size($f),
                'created_at' => date(DATE_ATOM, $disk->lastModified($f)),
            ])
            ->values()
            ->all();
    }

    public function isDue(Family $family): bool
    {
        return match ($family->backup_frequency) {
            'daily' => ! $family->last_backup_at || $family->last_backup_at->lte(now()->subHours(23)),
            'weekly' => ! $family->last_backup_at || $family->last_backup_at->lte(now()->subDays(7)->addHour()),
            default => false,
        };
    }

    private function rotate(Family $family): void
    {
        $disk = Storage::disk(config('familyhub.backup_disk'));
        collect($disk->files($this->directory($family)))
            ->sortDesc()
            ->slice(max(1, $family->backup_keep))
            ->each(fn ($f) => $disk->delete($f));
    }
}
