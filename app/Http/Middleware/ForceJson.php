<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every API answer is JSON, whether or not the client asked for it (many phone HTTP libraries do not send an Accept header). */
class ForceJson
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_contains((string) $request->header('Accept'), 'json')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
