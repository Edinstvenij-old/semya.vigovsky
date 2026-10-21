<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A private file (photo, scan, boarding pass). Files never get a public URL:
 * they are streamed through AttachmentController after an access check.
 */
#[Fillable(['family_id', 'attachable_type', 'attachable_id', 'uploaded_by', 'kind', 'disk', 'path', 'thumb_path', 'original_name', 'mime', 'size'])]
#[Hidden(['disk', 'path', 'thumb_path', 'attachable_type'])]
#[Appends(['url', 'thumb_url', 'is_image'])]
class Attachment extends Model
{
    use BelongsToFamily;

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $a) => Storage::disk($a->disk)->delete(array_filter([$a->path, $a->thumb_path])));
    }

    public static function store(Model $owner, UploadedFile $file, string $kind, int $userId, ?UploadedFile $thumb = null): self
    {
        $disk = config('familyhub.files_disk');
        $dir = "families/{$owner->family_id}/".$owner->getTable();
        $path = $file->store($dir, $disk);

        return $owner->attachments()->create([
            'family_id' => $owner->family_id,
            'uploaded_by' => $userId,
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'thumb_path' => $thumb?->store("{$dir}/thumbs", $disk) ?: null,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return "/api/attachments/{$this->id}";
    }

    /** Small preview for grids; falls back to the full file when there is no thumbnail. */
    public function getThumbUrlAttribute(): ?string
    {
        if ($this->thumb_path) {
            return "/api/attachments/{$this->id}/thumb";
        }

        return $this->is_image ? $this->url : null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
