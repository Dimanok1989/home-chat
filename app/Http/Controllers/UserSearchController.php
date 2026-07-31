<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['users' => []]);
        }

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);
        $pattern = '%'.$escaped.'%';

        $users = User::query()
            ->where('id', '!=', $currentUser->id)
            ->where(function ($builder) use ($pattern) {
                $builder->where('name', 'like', $pattern)
                    ->orWhere('username', 'like', $pattern);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'avatar_path', 'updated_at']);

        return response()->json([
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatarUrl(),
                'has_avatar' => (bool) $user->avatar_path,
                'initial' => $user->initial(),
            ])->values()->all(),
        ]);
    }
}
