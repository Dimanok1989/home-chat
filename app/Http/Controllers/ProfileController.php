<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'profile' => $this->formatProfile($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->name = trim($validated['name']);
        $user->last_name = isset($validated['last_name']) ? trim($validated['last_name']) : null;

        if ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($user);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($user);

            $file = $request->file('avatar');
            $extension = $file->guessExtension() ?? 'jpg';
            $path = $file->storeAs(
                'avatars',
                Str::uuid().'.'.$extension,
                'local',
            );

            $user->avatar_path = $path;
        }

        $user->save();

        return response()->json([
            'profile' => $this->formatProfile($user->fresh()),
        ]);
    }

    private function formatProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'display_name' => $user->displayName(),
            'username' => $user->username,
            'email' => $user->email,
            'has_avatar' => (bool) $user->avatar_path,
            'avatar_url' => $user->avatarUrl(),
            'initial' => $user->initial(),
        ];
    }

    private function deleteAvatar(User $user): void
    {
        if (! $user->avatar_path) {
            return;
        }

        Storage::disk('local')->delete($user->avatar_path);
    }
}
