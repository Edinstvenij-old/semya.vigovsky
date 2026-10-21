<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\TravelDocument;
use App\Services\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Streams private files after checking family membership (global scope) and 2FA for documents. */
class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment, TwoFactor $twoFactor): Response
    {
        if ($attachment->attachable_type === TravelDocument::class) {
            abort_unless($request->user()->hasTwoFactor() && $twoFactor->sessionVerified($request), 423, 'Требуется 2FA.');
        }

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $disposition = $attachment->is_image || $attachment->mime === 'application/pdf' ? 'inline' : 'attachment';

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=3600',
        ], $disposition);
    }

    public function thumb(Request $request, Attachment $attachment, TwoFactor $twoFactor): Response
    {
        if (! $attachment->thumb_path) {
            return $this->show($request, $attachment, $twoFactor);
        }

        if ($attachment->attachable_type === TravelDocument::class) {
            abort_unless($request->user()->hasTwoFactor() && $twoFactor->sessionVerified($request), 423, 'Требуется 2FA.');
        }

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->thumb_path), 404);

        return $disk->response($attachment->thumb_path, 'thumb.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    public function destroy(Request $request, Attachment $attachment, TwoFactor $twoFactor): JsonResponse
    {
        if ($attachment->attachable_type === TravelDocument::class) {
            abort_unless($twoFactor->sessionVerified($request), 423, 'Требуется 2FA.');
        }

        $attachment->delete();

        return response()->json(['ok' => true]);
    }
}
