<?php

namespace App\Http\Controllers;

use App\Models\LinkPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LinkPreviewImageController extends Controller
{
    public function show(Request $request, LinkPreview $linkPreview, string $token): BinaryFileResponse
    {
        if ($linkPreview->image_access_token === null
            || ! hash_equals($linkPreview->image_access_token, $token)) {
            abort(403);
        }

        if (! Auth::check()) {
            abort(403);
        }

        if ($linkPreview->image_disk === null || $linkPreview->image_path === null) {
            abort(404);
        }

        $disk = Storage::disk($linkPreview->image_disk);

        if (! $disk->exists($linkPreview->image_path)) {
            abort(404);
        }

        return response()->file(
            $disk->path($linkPreview->image_path),
            [
                'Content-Type' => $linkPreview->image_mime_type ?? 'application/octet-stream',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, max-age=86400',
            ],
        );
    }
}
