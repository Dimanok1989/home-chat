<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/chat');
        }

        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'privacy_consent' => ['accepted'],
        ], [
            'privacy_consent.accepted' => 'Необходимо согласие на обработку персональных данных.',
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            $field => $validated['login'],
            'password' => $validated['password'],
        ], $remember)) {
            return back()
                ->withInput($request->only('login', 'remember', 'privacy_consent'))
                ->withErrors(['login' => 'Неверный логин или пароль.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/chat');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
