<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Discover: short facts by topic and age (config/learn.php). Free for everyone. */
class LearnController extends Controller
{
    public function index(): JsonResponse
    {
        $topics = collect(config('learn.topics'))->map(fn ($t, $k) => ['topic' => $k, 'emoji' => $t['emoji'], 'color' => $t['color'], 'title' => $t['title'], 'about' => $t['about'], 'game' => $t['game'] ?? null, 'facts' => count(config("learn.facts.{$k}"))])->values()->all();

        return response()->json(['topics' => $topics]);
    }

    public function show(Request $request, string $topic): JsonResponse
    {
        $t = config("learn.topics.{$topic}");
        abort_if($t === null, 404);
        $age = $request->validate(['age' => 'sometimes|integer|between:2,18'])['age'] ?? null;

        $facts = collect(config("learn.facts.{$topic}"))
            ->filter(fn ($f) => $age === null || ($age >= $f[1][0] && $age <= $f[1][1]))
            ->map(fn ($f) => [
                'key' => $f[0], 'age' => $f[1], 'emoji' => $f[2], 'title' => ['en' => $f[3][0], 'ur' => $f[3][1]], 'text' => ['en' => $f[4][0], 'ur' => $f[4][1]],
                'question' => ['en' => $f[5][0], 'ur' => $f[5][1]], 'answer' => ['en' => $f[6][0], 'ur' => $f[6][1]],
            ])->values()->all();

        return response()->json(['topic' => $topic, 'emoji' => $t['emoji'], 'color' => $t['color'], 'title' => $t['title'], 'about' => $t['about'], 'game' => $t['game'] ?? null, 'age' => $age, 'facts' => $facts]);
    }
}
