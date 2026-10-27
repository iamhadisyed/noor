<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * A phone that loses signal mid-request cannot know whether the server did the work. It resends the same
 * write with the same `Idempotency-Key`; the server then answers with the first result instead of doing it twice.
 */
class Idempotent
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();
        if (! $key || ! $user || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        if (! preg_match('/^[A-Za-z0-9._\-:]{8,80}$/', $key)) {
            return response()->json(['message' => 'Idempotency-Key must be 8 to 80 letters, digits or . _ - :'], 422);
        }

        $path = '/' . ltrim($request->path(), '/');
        $row = DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->first();
        if ($row) {
            if ($row->method !== $request->method() || $row->path !== $path) {
                return response()->json(['message' => 'That Idempotency-Key was already used for a different request.'], 422);
            }

            return response($row->body, $row->status, ['Content-Type' => 'application/json', 'Idempotent-Replay' => 'true']);
        }

        $response = $next($request);
        if ($response->getStatusCode() < 300) {
            DB::table('idempotency_keys')->insertOrIgnore(['user_id' => $user->id, 'key' => $key, 'method' => $request->method(), 'path' => $path, 'status' => $response->getStatusCode(), 'body' => (string) $response->getContent(), 'created_at' => now()]);
        }

        return $response;
    }
}
