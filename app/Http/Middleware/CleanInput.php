<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes characters nobody types on purpose from every text field: control characters (a NUL byte breaks some databases)
 * and the invisible "right-to-left override" marks used to make one name look like another. Newlines and tabs stay.
 */
class CleanInput
{
    private const BAD = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson()) {
            $request->json()->replace($this->clean($request->json()->all()));
        } else {
            $request->request->replace($this->clean($request->request->all()));
        }
        $request->query->replace($this->clean($request->query->all()));

        return $next($request);
    }

    private function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->clean($v), $value);
        }

        return is_string($value) ? (preg_replace(self::BAD, '', $value) ?? $value) : $value;
    }
}
