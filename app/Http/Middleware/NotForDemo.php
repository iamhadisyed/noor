<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** The public demo accounts cannot do things that cost money, send email to strangers or delete the account. */
class NotForDemo
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->is_demo) {
            return response()->json(['message' => 'This is not available in the demo account.'], 403);
        }

        return $next($request);
    }
}
