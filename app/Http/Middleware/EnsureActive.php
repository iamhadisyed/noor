<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A suspended account loses access at once, even with a valid token. */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user && ! $user->isActive()) {
            $user->tokens()->delete();

            return response()->json(['message' => 'This account is unavailable.'], 403);
        }

        return $next($request);
    }
}
