<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain
            ? ApiToken::query()->where('token_hash', hash('sha256', $plain))->first()
            : null;

        $user = $token?->user;

        if (! $token || $token->expires_at === null || $token->expires_at->isPast() || ! $user instanceof User || $user->status !== User::STATUS_ACTIVE) {
            $token?->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('apiToken', $token);

        return $next($request);
    }
}
