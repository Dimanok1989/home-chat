<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatRoom;
use App\Models\InvitationToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Show the invitation management page (Vue SPA).
     */
    public function index(): View
    {
        return view('chat');
    }

    /**
     * Generate a new invitation token.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $token = Str::random(64);
        $expiresAt = $request->input('expires_in_hours')
            ? now()->addHours((int) $request->input('expires_in_hours'))
            : now()->addDays(7); // default: 7 days

        $invitation = InvitationToken::query()->create([
            'token' => $token,
            'created_by' => $user->id,
            'expires_at' => $expiresAt,
        ]);

        $url = route('register.invite', ['token' => $token]);

        return response()->json([
            'invitation' => [
                'id' => $invitation->id,
                'token' => $token,
                'url' => $url,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'used_at' => $invitation->used_at,
                'created_at' => $invitation->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * List active (unused, not expired) invitation tokens.
     */
    public function indexTokens(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $invitations = InvitationToken::query()
            ->where('created_by', $user->id)
            ->whereNull('used_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InvitationToken $invitation) => [
                'id' => $invitation->id,
                'token' => $invitation->token,
                'url' => route('register.invite', ['token' => $invitation->token]),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'created_at' => $invitation->created_at->toIso8601String(),
            ]);

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Show the registration form for invited users.
     */
    public function showRegistrationForm(string $token): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/chat');
        }

        $invitation = InvitationToken::query()->where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect('/')->withErrors(['link' => 'Ссылка приглашения недействительна или истекла.']);
        }

        return view('register', ['token' => $token]);
    }

    /**
     * Register a new user via invitation.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'exists:invitation_tokens,token'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $invitation = InvitationToken::query()->where('token', $validated['token'])->first();

        if (! $invitation || ! $invitation->isValid()) {
            return back()->withErrors(['token' => 'Ссылка приглашения недействительна или истекла.']);
        }

        $user = DB::transaction(function () use ($validated, $invitation) {
            $user = User::query()->create([
                'username' => $validated['username'],
                'name' => $validated['name'],
                'password' => bcrypt($validated['password']),
            ]);

            $invitation->markAsUsed();

            return $user;
        });

        // Send a system message to the global chat about the new user
        $globalRoom = ChatRoom::query()->where('type', ChatRoom::TYPE_GLOBAL)->first();

        if ($globalRoom) {
            $message = Message::query()->create([
                'user_id' => null,
                'chat_room_id' => $globalRoom->id,
                'body' => 'Новый пользователь [['.$user->name.'|'.$user->id.']] зарегистрировался.',
            ]);

            broadcast(new MessageSent($message));
        }

        // Log the user in
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/chat');
    }
}