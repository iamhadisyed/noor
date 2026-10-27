<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\PracticeMistakeLog;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Parent-facing numbers for one child. The web portal and the app draw the same JSON. */
class InsightsController extends Controller
{
    private function range(Request $request): array
    {
        $v = $request->validate(['days' => 'sometimes|integer|between:1,90', 'tz' => 'sometimes|timezone:all']);
        $tz = $v['tz'] ?? config('app.timezone');
        $to = CarbonImmutable::now($tz)->endOfDay();

        return [$to->subDays(($v['days'] ?? 7) - 1)->startOfDay(), $to, $tz];
    }

    public function summary(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        [$from, $to, $tz] = $this->range($request);

        $needs = ChildNeedsLog::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->get();
        $replies = $needs->whereNotNull('acknowledged_at')->map(fn ($n) => $n->created_at->diffInSeconds($n->acknowledged_at));

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'tz' => $tz],
            'practice' => $this->practice($child, $from, $to),
            'needs' => [
                'total' => $needs->count(),
                'by_type' => $needs->groupBy('need_type')->map->count()->sortDesc(),
                'by_hour' => collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $needs->filter(fn ($n) => $n->created_at->setTimezone($tz)->hour === $h)->count()]),
                'answered' => $needs->where('acknowledged', true)->count(),
                'unanswered' => $needs->where('acknowledged', false)->count(),
                'avg_reply_seconds' => $replies->isEmpty() ? null : (int) round($replies->avg()),
            ],
            'lessons' => [
                'published' => $child->lessons()->where('status', 'published')->count(),
                'drafts' => $child->lessons()->where('status', 'draft')->count(),
            ],
        ]);
    }

    /** Practice numbers for a child over a range. Also used for the therapist and teacher view, which never sees alerts. */
    public function practice(Child $child, $from, $to): array
    {
        $sessions = PracticeSession::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->get();
        $mistakes = PracticeMistakeLog::whereIn('session_id', $sessions->pluck('id'))->get();
        $attempts = $sessions->sum('total_attempts');

        return [
            'sessions' => $sessions->count(),
            'minutes' => (int) round($sessions->sum('duration_seconds') / 60),
            'attempts' => $attempts,
            'accuracy_pct' => $attempts ? (int) round($sessions->sum('successful_attempts') / $attempts * 100) : null,
            'by_language' => $sessions->groupBy('language')->map(fn ($g) => [
                'minutes' => (int) round($g->sum('duration_seconds') / 60),
                'accuracy_pct' => $g->sum('total_attempts') ? (int) round($g->sum('successful_attempts') / $g->sum('total_attempts') * 100) : null,
            ]),
            'mistake_types' => $mistakes->groupBy('mistake_type')->map->count(),
            'hardest_targets' => $mistakes->groupBy('target_string')->map->count()->sortDesc()->take(5),
            'avg_stroke_deviation' => $mistakes->whereNotNull('stroke_deviation_score')->avg('stroke_deviation_score') !== null
                ? round((float) $mistakes->whereNotNull('stroke_deviation_score')->avg('stroke_deviation_score'), 1) : null,
        ];
    }

    /** One row per day, zeros included, so a chart never has gaps. */
    public function daily(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        [$from, $to, $tz] = $this->range($request);

        $sessions = PracticeSession::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->get()
            ->groupBy(fn ($s) => $s->created_at->setTimezone($tz)->toDateString());
        $needs = ChildNeedsLog::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->get()
            ->groupBy(fn ($n) => $n->created_at->setTimezone($tz)->toDateString());

        $rows = [];
        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $key = $d->toDateString();
            $s = $sessions->get($key, collect());
            $rows[] = [
                'date' => $key, 'minutes' => (int) round($s->sum('duration_seconds') / 60), 'attempts' => $s->sum('total_attempts'),
                'successful' => $s->sum('successful_attempts'), 'alerts' => $needs->get($key, collect())->count(),
            ];
        }

        return response()->json($rows);
    }

    public function exportCsv(Request $request, int $childId): StreamedResponse
    {
        $child = ChildController::owned($request, $childId);
        [$from, $to] = $this->range($request);

        return response()->streamDownload(function () use ($child, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['type', 'when_utc', 'detail', 'value_1', 'value_2']);
            PracticeSession::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->orderBy('created_at')->each(function ($s) use ($out) {
                fputcsv($out, ['practice', $s->created_at->toIso8601String(), $s->language, "{$s->successful_attempts}/{$s->total_attempts}", $s->duration_seconds . 's']);
            });
            ChildNeedsLog::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->orderBy('created_at')->each(function ($n) use ($out) {
                fputcsv($out, ['alert', $n->created_at->toIso8601String(), $this->csvSafe($n->need_type), $n->acknowledged ? 'answered' : 'unanswered', $n->acknowledged_at ? $n->created_at->diffInSeconds($n->acknowledged_at) . 's' : '']);
            });
            fclose($out);
        }, "kindo-{$child->id}-report.csv", ['Content-Type' => 'text/csv']);
    }

    /** Stop spreadsheet formulas in exported text (a cell starting with = + - @ would run). */
    private function csvSafe(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    }
}
