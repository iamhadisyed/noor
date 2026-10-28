<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiDiagnosticReport;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\Lesson;
use App\Models\LessonTask;
use App\Models\PracticeSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform reporting for the super admin. Aggregates and operations only:
 * no child names, photos, voices or lesson media are ever returned from here.
 */
class AdminController extends Controller
{
    /** Tasks that belong to a parent's live or draft lessons (never the starter templates or archived lessons). */
    private function realTasks()
    {
        return LessonTask::whereHas('lesson', fn ($q) => $q->where('is_template', false)->where('status', '!=', 'archived'));
    }

    public function overview(): JsonResponse
    {
        $week = now()->subDays(7);
        $needs = ChildNeedsLog::where('created_at', '>=', $week)->get();
        $replies = $needs->whereNotNull('acknowledged_at')->map(fn ($n) => $n->created_at->diffInSeconds($n->acknowledged_at));

        return response()->json([
            'accounts' => [
                'total' => User::count(), 'new_7d' => User::where('created_at', '>=', $week)->count(),
                'active_7d' => User::where('last_login_at', '>=', $week)->count(),
                'pro' => User::where('subscription_tier', 'pro')->count(), 'free' => User::where('subscription_tier', 'free')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
            ],
            'children' => ['total' => Child::count(), 'special_track' => Child::where('track', 'special')->count()],
            'lessons' => [
                'published' => Lesson::where('is_template', false)->where('status', 'published')->count(),
                'drafts' => Lesson::where('is_template', false)->where('status', 'draft')->count(),
                'tasks' => $this->realTasks()->count(),
            ],
            'alerts_7d' => [
                'total' => $needs->count(), 'answered' => $needs->where('acknowledged', true)->count(),
                'avg_reply_seconds' => $replies->isEmpty() ? null : (int) round($replies->avg()),
                'unanswered_over_15m' => ChildNeedsLog::where('acknowledged', false)->where('created_at', '<', now()->subMinutes(15))->count(),
            ],
            'practice_7d' => ['sessions' => PracticeSession::where('created_at', '>=', $week)->count(), 'minutes' => (int) round(PracticeSession::where('created_at', '>=', $week)->sum('duration_seconds') / 60)],
            'ai_reports_7d' => ['generated' => AiDiagnosticReport::where('created_at', '>=', $week)->count()],
        ]);
    }

    /** One row per day, zeros included. */
    public function usage(Request $request): JsonResponse
    {
        $days = (int) ($request->validate(['days' => 'sometimes|integer|between:1,90'])['days'] ?? 14);
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $by = fn ($rows) => $rows->groupBy(fn ($r) => $r->created_at->toDateString())->map->count();
        $signups = $by(User::where('created_at', '>=', $from)->get(['created_at']));
        $logins = $by(AuditLog::where('action', 'auth.login')->where('created_at', '>=', $from)->get(['created_at']));
        $published = $by(AuditLog::where('action', 'lesson.published')->where('created_at', '>=', $from)->get(['created_at']));
        $alerts = $by(ChildNeedsLog::where('created_at', '>=', $from)->get(['created_at']));
        $sessions = $by(PracticeSession::where('created_at', '>=', $from)->get(['created_at']));

        $rows = [];
        for ($d = $from; $d->lte(now()); $d = $d->addDay()) {
            $k = $d->toDateString();
            $rows[] = ['date' => $k, 'signups' => $signups[$k] ?? 0, 'logins' => $logins[$k] ?? 0, 'lessons_published' => $published[$k] ?? 0, 'alerts' => $alerts[$k] ?? 0, 'practice_sessions' => $sessions[$k] ?? 0];
        }

        return response()->json($rows);
    }

    /** How good the content parents are building is, and where it is blocked. */
    public function quality(): JsonResponse
    {
        $lessons = Lesson::where('is_template', false)->where('status', 'draft')->with('tasks', 'child')->get();
        $blocked = $lessons->filter(fn ($l) => ! \App\Services\LessonRules::readiness($l)['ready'])->count();
        $missingSound = $this->realTasks()->get()->filter(fn ($t) => \App\Services\LessonRules::needsAudio($t) && ! $t->audio_path)->count();

        $needs = ChildNeedsLog::where('created_at', '>=', now()->subDays(30))->get();
        $bucket = fn ($n) => ! $n->acknowledged_at ? 'unanswered' : (($s = $n->created_at->diffInSeconds($n->acknowledged_at)) < 60 ? 'under_1m' : ($s < 300 ? '1_to_5m' : ($s < 900 ? '5_to_15m' : 'over_15m')));

        return response()->json([
            'tasks_by_type' => $this->realTasks()->select('type', DB::raw('count(*) as n'))->groupBy('type')->pluck('n', 'type'),
            'draft_lessons_blocked' => $blocked, 'draft_lessons' => $lessons->count(), 'tasks_missing_required_sound' => $missingSound,
            'reply_time_30d' => collect(['under_1m', '1_to_5m', '5_to_15m', 'over_15m', 'unanswered'])->mapWithKeys(fn ($b) => [$b => $needs->filter(fn ($n) => $bucket($n) === $b)->count()]),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $hours = (int) ($request->query('hours') ?: 24);
        $rows = ChildNeedsLog::where('created_at', '>=', now()->subHours(min($hours, 720)))->get();

        return response()->json([
            'by_hour' => collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $rows->filter(fn ($n) => $n->created_at->hour === $h)->count()]),
            'by_need' => $rows->groupBy('need_type')->map->count()->sortDesc(),
            'unanswered_over_15m' => ChildNeedsLog::where('acknowledged', false)->where('created_at', '<', now()->subMinutes(15))->latest('created_at')->limit(50)->get()
                ->map(fn ($n) => ['id' => $n->id, 'child_id' => $n->child_id, 'need_type' => $n->need_type, 'minutes_waiting' => (int) $n->created_at->diffInMinutes(now())]),
        ]);
    }

    public function aiReports(): JsonResponse
    {
        $week = now()->subDays(7);
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('payload', 'like', '%GeneratePediatricReportJob%')->where('failed_at', '>=', $week)->count() : 0;

        return response()->json([
            'generated_7d' => AiDiagnosticReport::where('created_at', '>=', $week)->count(),
            'by_provider_7d' => AiDiagnosticReport::where('created_at', '>=', $week)->select('provider', DB::raw('count(*) as n'))->groupBy('provider')->pluck('n', 'provider'),
            'failed_7d' => $failed,
        ]);
    }

    /** Settings that are safe on a laptop but dangerous or broken on a live site. Shown first on the Health page. */
    private function configWarnings(): array
    {
        $w = [];
        if (config('app.debug')) {
            $w[] = ['key' => 'debug', 'level' => 'danger', 'message' => 'Debug mode is on (APP_DEBUG=true). Error pages can show passwords and keys to anyone. Set APP_DEBUG=false.'];
        }
        if (config('app.env') !== 'production') {
            $w[] = ['key' => 'env', 'level' => 'warn', 'message' => 'APP_ENV is "'.config('app.env').'". Set APP_ENV=production on the live site.'];
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $w[] = ['key' => 'https', 'level' => 'warn', 'message' => 'APP_URL does not start with https://. Picture and sound links, and sign-in, should use https.'];
        }
        if (! config('kindo.backup.password')) {
            $w[] = ['key' => 'backup', 'level' => 'warn', 'message' => 'KINDO_BACKUP_PASSWORD is not set, so backups cannot be made.'];
        }
        if (! is_file((string) config('kindo.fcm.service_account')) || ! config('kindo.fcm.project_id')) {
            $w[] = ['key' => 'push', 'level' => 'info', 'message' => 'Push notifications are not set up (Firebase service account and FCM_PROJECT_ID).'];
        }
        if (! config('kindo.groq_keys') && ! config('kindo.gemini_keys')) {
            $w[] = ['key' => 'ai', 'level' => 'info', 'message' => 'No Groq or Gemini keys: AI reports and the photo lesson importer will not work.'];
        }

        return $w;
    }

    public function health(): JsonResponse
    {
        $last = Cache::get('scheduler:last_run');
        $db = true;
        try { DB::select('select 1'); } catch (\Throwable) { $db = false; }

        return response()->json([
            'database' => $db,
            'queue' => ['pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null, 'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null],
            'scheduler' => ['last_run' => $last, 'alive' => $last ? CarbonImmutable::parse($last)->gt(now()->subMinutes(5)) : false],
            'mail' => ['driver' => config('mail.default')],
            'warnings' => $this->configWarnings(),
            'backups' => app(\App\Services\BackupService::class)->status(),
            'storage' => ['media_files' => $this->realTasks()->whereNotNull('image_path')->count() + $this->realTasks()->whereNotNull('audio_path')->count()],
            'php' => PHP_VERSION, 'time' => now()->toIso8601String(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $q = User::query()->withCount('children')->orderByDesc('id');
        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        $page = $q->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn ($u) => [
                'id' => $u->id, 'email' => $u->email, 'name' => $u->name, 'role' => $u->role, 'status' => $u->status,
                'plan' => $u->isPro() ? 'pro' : 'free', 'verified' => (bool) $u->email_verified_at, 'children' => $u->children_count,
                'last_login_at' => $u->last_login_at, 'created_at' => $u->created_at, 'status_reason' => $u->status_reason, 'terms_version' => $u->terms_version,
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $data = $request->validate(['status' => 'sometimes|in:active,inactive,suspended', 'reason' => 'nullable|string|max:300', 'plan' => 'sometimes|in:free,pro', 'subscription_expires_at' => 'sometimes|nullable|date|after:now']);

        if (($user->isSuperAdmin() || $user->id === $request->user()->id) && isset($data['status']) && $data['status'] !== 'active') {
            return response()->json(['message' => 'An administrator cannot be made inactive here.'], 422);
        }

        $changes = [];
        if (isset($data['status'])) {
            $changes['status'] = $data['status'];
            $changes['status_reason'] = $data['status'] === 'active' ? null : ($data['reason'] ?? null);
        }
        if (isset($data['plan'])) { $changes['subscription_tier'] = $data['plan']; }
        if (array_key_exists('subscription_expires_at', $data)) { $changes['subscription_expires_at'] = $data['subscription_expires_at']; }
        $user->update($changes);

        if (isset($changes['status']) && $changes['status'] !== 'active') {
            $user->tokens()->delete();
        }
        AuditLog::record($request->user(), 'admin.user_updated', $user, $changes);

        return response()->json(['id' => $user->id, 'status' => $user->status, 'plan' => $user->isPro() ? 'pro' : 'free']);
    }

    public function audit(Request $request): JsonResponse
    {
        $q = AuditLog::query()->orderByDesc('id');
        if ($a = $request->query('action')) { $q->where('action', 'like', $a . '%'); }
        if ($actor = $request->query('actor_id')) { $q->where('actor_id', (int) $actor); }
        $page = $q->paginate(50);

        return response()->json(['data' => $page->items(), 'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }
}
