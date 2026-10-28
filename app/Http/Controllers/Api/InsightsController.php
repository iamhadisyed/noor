<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\PracticeMistakeLog;
use App\Models\PracticeSession;
use App\Models\SessionLetter;
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

    /** Per-letter tracing results over a range: accuracy, steadiness, how much help was needed and whether it is improving. */
    public function letterStats(Child $child, $from, $to): array
    {
        $rows = SessionLetter::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->orderBy('id')->get();
        $pct = fn ($rs) => $rs->sum('attempts') ? (int) round($rs->sum('successes') / $rs->sum('attempts') * 100) : null;

        $letters = $rows->groupBy(fn ($r) => $r->language.'|'.$r->letter)->map(function ($g) use ($pct) {
            $attempts = $g->sum('attempts');
            $acc = $pct($g);
            $assisted = (int) round($g->sum('assisted') / max(1, $attempts) * 100);
            $half = intdiv($g->count(), 2);
            $early = $g->take($half);
            $late = $g->skip($half);
            $trend = null;
            if ($half >= 1 && $early->sum('attempts') >= 3 && $late->sum('attempts') >= 3) {
                $d = $pct($late) - $pct($early);
                $trend = $d >= 10 ? 'up' : ($d <= -10 ? 'down' : 'steady');
            }
            $dev = $g->whereNotNull('deviation')->avg('deviation');
            // Needs help: low accuracy, or mostly done with the guide on. Mastered needs enough tries to mean it.
            $status = $attempts < 3 ? 'new' : ($acc < 50 ? 'needs_help' : ($acc >= 85 && $assisted <= 25 && $attempts >= 5 ? 'mastered' : 'practising'));

            return ['letter' => $g->first()->letter, 'language' => $g->first()->language, 'attempts' => $attempts, 'accuracy_pct' => $acc, 'assisted_pct' => $assisted,
                'avg_deviation' => $dev === null ? null : round((float) $dev, 1), 'trend' => $trend, 'status' => $status];
        })->sortBy([['accuracy_pct', 'asc'], ['attempts', 'desc']])->values();

        return [
            'letters' => $letters->all(),
            'focus' => $letters->whereIn('status', ['needs_help', 'practising'])->take(3)->pluck('letter')->values()->all(),
            'mastered' => $letters->where('status', 'mastered')->count(),
        ];
    }

    public function letters(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        [$from, $to, $tz] = $this->range($request);

        return response()->json(['range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'tz' => $tz]] + $this->letterStats($child, $from, $to));
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

    /** A printable progress report for a parent to keep or hand to a teacher or therapist. */
    public function exportPdf(Request $request, int $childId)
    {
        $child = ChildController::owned($request, $childId);
        [$from, $to, $tz] = $this->range($request);
        $summary = $this->summary($request, $childId)->getData(true);
        $letters = $this->letterStats($child, $from, $to);
        $ur = function (?string $t, int $px = 64, string $c = '#120c15', string $css = 'glyph') {
            $img = preg_match('/\p{Arabic}/u', (string) $t) ? \App\Support\UrduText::imageDataUri($t, $px, $c) : null;

            return new \Illuminate\Support\HtmlString($img ? '<img src="'.$img.'" class="'.$css.'" alt="">' : e((string) $t));
        };

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.progress-report', [
            'child' => $child, 'from' => $from, 'to' => $to, 's' => $summary, 'letters' => $letters, 'ur' => $ur,
            'milestones' => array_slice(app(\App\Services\Milestones::class)->for($child), 0, 6),
        ])->setPaper('a4', 'portrait')->download("kindo-{$child->id}-progress-report.pdf");
    }

    /** Stop spreadsheet formulas in exported text (a cell starting with = + - @ would run). */
    private function csvSafe(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    }
}
