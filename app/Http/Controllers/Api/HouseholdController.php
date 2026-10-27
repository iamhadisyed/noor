<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\HouseholdInviteMail;
use App\Models\AuditLog;
use App\Models\HouseholdMember;
use App\Models\SyncChange;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Two parents (or a parent and a grandparent) sharing the same children. The owner invites by email; the invited
 * person signs in with that email and accepts. Either can then work with the children and answer alerts.
 * Only the owner can delete a child, invite or remove people, or delete the account.
 */
class HouseholdController extends Controller
{
    private function member(HouseholdMember $m): array
    {
        return ['id' => $m->id, 'email' => $m->email, 'name' => $m->user?->name, 'status' => $m->status, 'accepted_at' => $m->accepted_at];
    }

    public function show(Request $request): JsonResponse
    {
        $me = $request->user();

        return response()->json([
            'max_members' => (int) config('kindo.household_max'),
            'members' => HouseholdMember::where('owner_id', $me->id)->with('user:id,name')->orderBy('id')->get()->map(fn ($m) => $this->member($m))->all(),
            'joined' => HouseholdMember::where('user_id', $me->id)->where('status', 'active')->with('owner:id,name,email')->get()->map(fn ($m) => ['id' => $m->id, 'owner' => ['name' => $m->owner->name, 'email' => $m->owner->email]])->all(),
            'invitations' => HouseholdMember::where('email', $me->email)->where('status', 'invited')->with('owner:id,name,email')->get()->map(fn ($m) => ['id' => $m->id, 'from' => ['name' => $m->owner->name, 'email' => $m->owner->email]])->all(),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $me = $request->user();
        $email = strtolower($request->validate(['email' => 'required|email:rfc|max:191'])['email']);

        if ($email === strtolower($me->email)) {
            return response()->json(['message' => 'That is your own email address.', 'errors' => ['email' => ['That is your own email address.']]], 422);
        }
        if (HouseholdMember::where('owner_id', $me->id)->count() >= (int) config('kindo.household_max')) {
            return response()->json(['message' => 'You can share with up to ' . config('kindo.household_max') . ' people. Remove someone first.'], 422);
        }
        if (HouseholdMember::where('owner_id', $me->id)->where('email', $email)->exists()) {
            return response()->json(['message' => 'That person is already invited.', 'errors' => ['email' => ['That person is already invited.']]], 422);
        }

        $m = HouseholdMember::create(['owner_id' => $me->id, 'email' => $email, 'status' => 'invited', 'invited_by' => $me->id]);
        try {
            Mail::to($email)->send(new HouseholdInviteMail($me->name ?: $me->email, rtrim((string) config('app.url'), '/') . '/portal/'));
        } catch (\Throwable $e) {
            Log::error('Household invite email failed: ' . $e->getMessage());
        }
        AuditLog::record($me, 'household.invited', $m, ['email_hash' => substr(hash('sha256', $email), 0, 12)]);

        return response()->json($this->member($m), 201);
    }

    /** The owner takes someone out, or cancels an invitation that was not accepted. */
    public function remove(Request $request, int $id): JsonResponse
    {
        $m = HouseholdMember::where('owner_id', $request->user()->id)->findOrFail($id);
        $this->end($m, $request->user(), 'household.removed');

        return response()->json(['ok' => true]);
    }

    /** A co-parent leaves a household they joined. */
    public function leave(Request $request, int $id): JsonResponse
    {
        $m = HouseholdMember::where('user_id', $request->user()->id)->where('status', 'active')->findOrFail($id);
        $this->end($m, $request->user(), 'household.left');

        return response()->json(['ok' => true]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $me = $request->user();
        $m = HouseholdMember::where('email', $me->email)->where('status', 'invited')->findOrFail($id);
        $m->update(['user_id' => $me->id, 'status' => 'active', 'accepted_at' => now()]);
        SyncChange::reset($me->id);   // their phone must reload: they can now see more children
        AuditLog::record($me, 'household.accepted', $m);

        return $this->show($request);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $m = HouseholdMember::where('email', $request->user()->email)->where('status', 'invited')->findOrFail($id);
        $m->delete();
        AuditLog::record($request->user(), 'household.declined', null, ['owner_id' => $m->owner_id]);

        return response()->json(['ok' => true]);
    }

    private function end(HouseholdMember $m, User $actor, string $action): void
    {
        $wasActive = $m->status === 'active' && $m->user_id;
        $userId = $m->user_id;
        $m->delete();
        if ($wasActive) {
            SyncChange::reset($userId);   // the removed parent's phone reloads and no longer sees these children
        }
        AuditLog::record($actor, $action, null, ['owner_id' => $m->owner_id, 'member_user_id' => $userId]);
    }
}
