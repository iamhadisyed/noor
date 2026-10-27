<?php

namespace App\Jobs;

use App\Models\AiDiagnosticReport;
use App\Models\Child;
use App\Services\AIServiceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePediatricReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(protected int $childId) {}

    public function handle(AIServiceManager $aiManager): void
    {
        $since = now()->subDays(7);

        $child = Child::with([
            'practiceSessions' => fn ($q) => $q->where('created_at', '>=', $since),
            'practiceSessions.mistakes',
            'needsLogs' => fn ($q) => $q->where('created_at', '>=', $since),
        ])->findOrFail($this->childId);

        $payload = [
            'child_age_years' => $child->date_of_birth->diffInYears(now()),
            'neurotype' => $child->neurotype,
            'recent_mistakes' => $child->practiceSessions->flatMap->mistakes->take(30)->map(fn ($m) => [
                'target' => $m->target_string,
                'input' => $m->attempted_string,
                'type' => $m->mistake_type,
            ])->values()->all(),
            'potty_frequency_last_7_days' => $child->needsLogs->where('need_type', 'potty')->count(),
            'potty_hours_of_day' => $child->needsLogs->where('need_type', 'potty')
                ->map(fn ($l) => $l->created_at->format('H'))->values()->all(),
        ];

        $result = $aiManager->generateDiagnosticReport($payload);

        if (! $result) {
            $this->fail(new \RuntimeException("No AI provider produced a report for child {$child->id}"));
            return;
        }

        AiDiagnosticReport::updateOrCreate(
            ['child_id' => $child->id, 'report_week_start' => now()->startOfWeek()->toDateString()],
            [
                'language_dominance' => $result['language_dominance'] ?? null,
                'provider' => $result['_provider'] ?? null,
                'phonetic_blindspots' => $result['phonetic_blindspots'] ?? [],
                'potty_timing_analysis' => ['summary' => $result['potty_routine_summary'] ?? '', 'tips' => $result['parent_tips'] ?? []],
                'sensory_behavior_summary' => $result['sensory_behavior_analysis'] ?? '',
                'actionable_parent_exercises' => $result['custom_exercises'] ?? [],
                'dietary_chaining_tips' => $result['dietary_chaining_tips'] ?? [],
            ]
        );
    }
}
