<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parent guides (config/guidance.php): general information, never medical advice. A Pro feature. The disclaimer always travels with the
 * content, and the apps and the portal show it before anything else.
 */
class GuidanceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['guides' => collect(config('guidance'))->map(fn ($g, $k) => ['guide' => $k, 'title' => $g['title']])->values()->all(), 'pro' => true]);
    }

    public function show(Request $request, string $guide): JsonResponse
    {
        $g = config("guidance.{$guide}");
        abort_if($g === null, 404);

        if (! $request->user()->hasProAccess()) {
            return response()->json([
                'message' => 'The parent guides are part of Kindo Pro.',
                'upgrade' => true,
                'guide' => $guide,
                'title' => $g['title'],
                'preview' => array_map(fn ($s) => ['key' => $s['key'], 'title' => $s['title']], $g['sections']),
            ], 402);
        }

        return response()->json(['guide' => $guide] + $g);
    }
}
