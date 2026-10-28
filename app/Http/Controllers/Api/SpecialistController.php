<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\GameProgress;
use App\Models\PracticeSession;
use App\Models\SpecialistAccess;
use App\Services\FcmService;
use App\Services\Household;
use App\Services\NotificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Therapists and teachers. A parent creates a short code for one child; the specialist signs in with their own email,
 * enters it, and can then read the child's practice progress and set exercises. They never see alerts, pictures,
 * voices or family details, cannot change anything about the child, and the parent can end it at any time.
 */
class SpecialistController extends Controller
{
    private const CODE_HOURS = 24;

    // ---------- the parent's side ----------

    private function presentAccess(SpecialistAccess $a): array
    {
        return ['id' => $a->id, 'child_id' => $a->child_id, 'role' => $a->role, 'label' => $a->label, 'status' => $a->status,
            'code_expires_at' => $a->status === 'pending' ? $a->code_expires_at : null, 'code_expired' => $a->status === 'pending' && $a->code_expires_at && $a->code_expires_at->isPast(),
            'specialist' => $a->user ? ['name' => $a->user->name, 'email' => $a->user->email] : null, 'redeemed_at' => $a->redeemed_at, 'last_viewed_at' => $a->last_viewed_at];
    }

    private function ownedAccess(Request $request, int $id): SpecialistAccess
    {
        return SpecialistAccess::whereIn('child_id', $request->user()->accessibleChildren()->select('id'))->where('status', '!=', 'revoked')->findOrFail($id);
    }

    private function newCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (SpecialistAccess::where('code_hash', SpecialistAccess::hashCode($code))->where('code_expires_at', '>', now())->exists());

        return $code;
    }

    public function index(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        return response()->json(SpecialistAccess::where('child_id', $child->id)->where('status', '!=', 'revoked')->with('user:id,name,email')->orderBy('id')->get()->map(fn ($a) => $this->presentAccess($a))->all());
    }

    /** Creates the access and shows its code once. */
    public function invite(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $data = $request->validate(['role' => 'required|in:therapist,teacher', 'label' => 'required|string|max:80']);
        if (SpecialistAccess::where('child_id', $child->id)->where('status', '!=', 'revoked')->count() >= (int) config('kindo.specialists_max')) {
            return response()->json(['message' => 'You can share one child with up to ' . config('kindo.specialists_max') . ' therapists or teachers. Remove someone first.'], 422);
        }

        $code = $this->newCode();
        $a = SpecialistAccess::create($data + ['child_id' => $child->id, 'status' => 'pending', 'code_hash' => SpecialistAccess::hashCode($code), 'code_expires_at' => now()->addHours(self::CODE_HOURS), 'created_by' => $request->user()->id]);
        AuditLog::record($request->user(), 'specialist.invited', $a, ['role' => $a->role]);

        return response()->json($this->presentAccess($a) + ['code' => $code], 201);
    }

    /** A fresh code for someone who has not used theirs yet (or whose code ran out). */
    public function newCodeFor(Request $request, int $id): JsonResponse
    {
        $a = $this->ownedAccess($request, $id);
        if ($a->status !== 'pending') {
            return response()->json(['message' => 'This person already has access.'], 422);
        }
        $code = $this->newCode();
        $a->update(['code_hash' => SpecialistAccess::hashCode($code), 'code_expires_at' => now()->addHours(self::CODE_HOURS)]);

        return response()->json($this->presentAccess($a->fresh()) + ['code' => $code]);
    }

    /** Ends access at once, or cancels a code nobody used. */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $a = $this->ownedAccess($request, $id);
        $a->update(['status' => 'revoked', 'revoked_at' => now(), 'code_hash' => null]);
        AuditLog::record($request->user(), 'specialist.revoked', $a);

        return response()->json(['ok' => true]);
    }

    public function parentAssignments(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        return response()->json(Assignment::where('child_id', $child->id)->with('author:id,name,email')->orderByRaw("case status when 'open' then 0 else 1 end")->orderByDesc('id')->limit(100)->get()->map(fn ($a) => self::presentAssignment($a))->all());
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $a = Assignment::whereIn('child_id', $request->user()->accessibleChildren()->select('id'))->findOrFail($id);
        $done = $request->validate(['done' => 'sometimes|boolean'])['done'] ?? true;
        $a->update(['status' => $done ? 'done' : 'open', 'done_at' => $done ? now() : null]);

        return response()->json(self::presentAssignment($a->fresh()->load('author:id,name,email')));
    }

    public static function presentAssignment(Assignment $a): array
    {
        return ['id' => $a->id, 'child_id' => $a->child_id, 'title' => $a->title, 'note' => $a->note, 'due_on' => $a->due_on, 'status' => $a->status, 'done_at' => $a->done_at, 'created_at' => $a->created_at,
            'from' => $a->author ? ['name' => $a->author->name, 'email' => $a->author->email] : null];
    }

    // ---------- the specialist's side ----------

    /** Enter a code. Every refusal reads the same. */
    public function redeem(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => 'required|string|max:12'])['code'];
        $digits = preg_replace('/\D/', '', $code);
        $a = strlen($digits) === 6 ? SpecialistAccess::where('code_hash', SpecialistAccess::hashCode($digits))->where('status', 'pending')->where('code_expires_at', '>', now())->first() : null;

        if (! $a) {
            AuditLog::record($request->user(), 'specialist.redeem_failed');

            return response()->json(['message' => 'That code is not valid, has been used, or has expired. Ask the parent for a new one.', 'errors' => ['code' => ['That code is not valid, has been used, or has expired. Ask the parent for a new one.']]], 422);
        }
        if ($a->child->user_id === $request->user()->id || $request->user()->accessibleChildren()->whereKey($a->child_id)->exists()) {
            return response()->json(['message' => 'This is your own family. The code is for a therapist or teacher.'], 422);
        }
        if (SpecialistAccess::where('child_id', $a->child_id)->where('user_id', $request->user()->id)->where('status', 'active')->exists()) {
            return response()->json(['message' => 'You already have access to this child.'], 422);
        }

        $a->update(['user_id' => $request->user()->id, 'status' => 'active', 'redeemed_at' => now(), 'code_hash' => null, 'code_expires_at' => null]);
        AuditLog::record($request->user(), 'specialist.redeemed', $a);

        return response()->json($this->presentShared($a->fresh()->load('child')));
    }

    private function presentShared(SpecialistAccess $a): array
    {
        $c = $a->child;
        $s = array_merge($c->track === 'special' ? ChildController::SUPPORT_SPECIAL : ChildController::SUPPORT_STANDARD, $c->support_profile ?? []);

        return ['access_id' => $a->id, 'child_id' => $c->id, 'name' => $c->name, 'age_years' => $c->date_of_birth->age, 'track' => $c->track, 'neurotype' => $c->neurotype, 'support' => $s, 'role' => $a->role, 'label' => $a->label];
    }

    private function mine(Request $request, int $childId): SpecialistAccess
    {
        return SpecialistAccess::where('user_id', $request->user()->id)->where('status', 'active')->where('child_id', $childId)->with('child')->firstOrFail();
    }

    public function shared(Request $request): JsonResponse
    {
        return response()->json(SpecialistAccess::where('user_id', $request->user()->id)->where('status', 'active')->with('child')->orderBy('id')->get()->map(fn ($a) => $this->presentShared($a))->all());
    }

    public function leave(Request $request, int $childId): JsonResponse
    {
        $a = $this->mine($request, $childId);
        $a->update(['status' => 'revoked', 'revoked_at' => now()]);
        AuditLog::record($request->user(), 'specialist.left', $a);

        return response()->json(['ok' => true]);
    }

    /** Practice progress and the lessons' titles. No alerts, pictures, voices or family. */
    public function summary(Request $request, int $childId): JsonResponse
    {
        $a = $this->mine($request, $childId);
        $child = $a->child;
        $v = $request->validate(['days' => 'sometimes|integer|between:1,90', 'tz' => 'sometimes|timezone:all']);
        $tz = $v['tz'] ?? config('app.timezone');
        $to = CarbonImmutable::now($tz)->endOfDay();
        $from = $to->subDays(($v['days'] ?? 14) - 1)->startOfDay();

        $a->update(['last_viewed_at' => now()]);
        $days = PracticeSession::where('child_id', $child->id)->whereBetween('created_at', [$from, $to])->get()->groupBy(fn ($s) => $s->created_at->setTimezone($tz)->toDateString());
        $daily = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $g = $days->get($d->toDateString(), collect());
            $att = $g->sum('total_attempts');
            $daily[] = ['date' => $d->toDateString(), 'minutes' => (int) round($g->sum('duration_seconds') / 60), 'sessions' => $g->count(), 'accuracy_pct' => $att ? (int) round($g->sum('successful_attempts') / $att * 100) : null];
        }

        return response()->json($this->presentShared($a) + [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'tz' => $tz],
            'practice' => app(InsightsController::class)->practice($child, $from, $to),
            'daily' => $daily,
            'letters' => app(InsightsController::class)->letterStats($child, $from, $to),
            'games' => GameProgress::where('child_id', $child->id)->get()->map(fn ($g) => ['game' => $g->game, 'level' => $g->level, 'plays' => $g->plays, 'last_accuracy' => $g->last_accuracy])->all(),
            'lessons' => $child->lessons()->where('is_template', false)->where('status', '!=', 'archived')->withCount('tasks')->orderBy('week_number')->get()->map(fn ($l) => ['title' => $l->title, 'status' => $l->status, 'week' => $l->week_number, 'language' => $l->language, 'tasks' => $l->tasks_count])->all(),
        ]);
    }

    public function myAssignments(Request $request, int $childId): JsonResponse
    {
        $this->mine($request, $childId);

        return response()->json(Assignment::where('child_id', $childId)->where('created_by', $request->user()->id)->with('author:id,name,email')->orderByDesc('id')->limit(100)->get()->map(fn ($a) => self::presentAssignment($a))->all());
    }

    public function assign(Request $request, int $childId): JsonResponse
    {
        $access = $this->mine($request, $childId);
        $data = $request->validate(['title' => 'required|string|max:120', 'note' => 'nullable|string|max:1000', 'due_on' => 'nullable|date|after_or_equal:today']);
        $a = Assignment::create($data + ['child_id' => $childId, 'created_by' => $request->user()->id, 'status' => 'open']);
        app(\App\Services\ContentFilter::class)->scanAssignment($a);
        AuditLog::record($request->user(), 'specialist.assigned', $a);

        // the parents hear about it, like any other gentle notification
        $parents = \App\Models\User::whereIn('id', Household::userIdsFor($access->child))->get();
        foreach ($parents->filter(fn ($p) => NotificationPolicy::shouldPush($p, 'assignment'))->pluck('id') as $pid) {
            app(FcmService::class)->sendToUser($pid, "New exercise from {$access->label}", $a->title, ['type' => 'assignment', 'child_id' => $childId, 'assignment_id' => $a->id]);
        }

        return response()->json(self::presentAssignment($a->load('author:id,name,email')), 201);
    }

    private function ownAssignment(Request $request, int $id): Assignment
    {
        $a = Assignment::where('created_by', $request->user()->id)->findOrFail($id);
        $this->mine($request, $a->child_id);   // and the access must still be active

        return $a;
    }

    public function updateAssignment(Request $request, int $id): JsonResponse
    {
        $a = $this->ownAssignment($request, $id);
        $a->update($request->validate(['title' => 'sometimes|string|max:120', 'note' => 'sometimes|nullable|string|max:1000', 'due_on' => 'sometimes|nullable|date']));
        app(\App\Services\ContentFilter::class)->scanAssignment($a);

        return response()->json(self::presentAssignment($a->fresh()->load('author:id,name,email')));
    }

    public function deleteAssignment(Request $request, int $id): JsonResponse
    {
        $this->ownAssignment($request, $id)->delete();

        return response()->json(['ok' => true]);
    }
}
