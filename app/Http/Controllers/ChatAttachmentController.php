<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatAttachmentController extends Controller
{
    public function show(Request $request, MessageAttachment $attachment, string $token): BinaryFileResponse
    {
        if (! hash_equals($attachment->access_token, $token)) {
            abort(403);
        }

        if ($attachment->token_expires_at === null || $attachment->token_expires_at->isPast()) {
            abort(403, 'Ссылка на файл истекла');
        }

        if (! Auth::check()) {
            abort(403);
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response()->file(
            $disk->path($attachment->path),
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
