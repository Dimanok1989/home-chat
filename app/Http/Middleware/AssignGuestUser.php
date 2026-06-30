<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignGuestUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('guest_id')) {
            $request->session()->put('guest_id', (string) Str::uuid());
        }

        if (! Auth::check()) {
            Auth::setUser(new GenericUser([
                'id' => $request->session()->get('guest_id'),
                'name' => $request->ip(),
            ]));
        }

        return $next($request);
    }
}
