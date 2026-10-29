<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ProApprovedMail;
use App\Mail\ProUpgradeAdminMail;
use App\Mail\ProUpgradeInstructionsMail;
use App\Models\AuditLog;
use App\Models\UpgradeRequest;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Getting Pro by hand. A parent taps "Make me Pro" (we email how to pay), then "I have paid" with how they paid, and the owner checks it and
 * switches the account to Pro (admin side below). No payment is taken in the app.
 */
class UpgradeController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public static function present(UpgradeRequest $r): array
    {
        return ['id' => $r->id, 'status' => $r->status, 'requested_at' => $r->created_at, 'paid_reported_at' => $r->paid_reported_at, 'payment_method' => $r->payment_method,
            'payment_reference' => $r->payment_reference, 'days_granted' => $r->days_granted, 'admin_note' => $r->status === 'rejected' ? $r->admin_note : null, 'handled_at' => $r->handled_at];
    }

    private function open(User $user): ?UpgradeRequest
    {
        return UpgradeRequest::where('user_id', $user->id)->whereIn('status', ['requested', 'paid_reported'])->latest('id')->first();
    }

    private function tellOwners(string $kind, User $user, ?string $phone, ?string $detail): void
    {
        $to = config('kindo.super_admins', []);
        if ($to === []) {
            Log::warning('A Pro request came in but no super admin email is configured.');

            return;
        }
        try {
            Mail::to($to)->send(new ProUpgradeAdminMail($kind, $user->email, $user->name ?: $user->email, $phone, $detail));
        } catch (\Throwable $e) {
            Log::error('Pro request email to the owner failed: ' . $e->getMessage());
        }
    }

    /** The current request, if there is one. */
    public function show(Request $request): JsonResponse
    {
        $r = UpgradeRequest::where('user_id', $request->user()->id)->latest('id')->first();

        return response()->json(['request' => $r ? self::present($r) : null, 'payment' => ['contact_email' => config('kindo.payment.contact_email'), 'instructions' => config('kindo.payment.instructions')]]);
    }

    public function request(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isPro() && ! $user->subscription_expires_at) {
            return response()->json(['message' => 'Your Pro plan has no end date, so there is nothing to buy.'], 422);
        }
        $data = $request->validate(['phone' => 'nullable|string|max:30', 'note' => 'nullable|string|max:500']);
        $existing = $this->open($user);
        if ($existing) {
            return response()->json(['message' => 'You have already asked for Pro. We emailed you how to pay.', 'request' => self::present($existing)], 200);
        }

        $r = UpgradeRequest::create(['user_id' => $user->id, 'status' => 'requested', 'phone' => $data['phone'] ?? $user->phone, 'note' => $data['note'] ?? null]);
        try {
            Mail::to($user->email)->send(new ProUpgradeInstructionsMail($user->name ?: 'there', config('kindo.payment.instructions'), config('kindo.payment.contact_email')));
        } catch (\Throwable $e) {
            Log::error('Pro instructions email failed: ' . $e->getMessage());
        }
        $this->tellOwners('requested', $user, $r->phone, $r->note);
        AuditLog::record($user, 'billing.pro_requested', $r);

        return response()->json(['request' => self::present($r), 'message' => 'Thank you. We have emailed you how to pay.'], 201);
    }

    public function paid(Request $request): JsonResponse
    {
        $user = $request->user();
        $r = $this->open($user);
        if (! $r) {
            return response()->json(['message' => 'Please tap "Make me Pro" first, so we can email you how to pay.'], 422);
        }
        $data = $request->validate(['payment_method' => 'required|string|max:40', 'payment_reference' => 'required|string|max:120', 'payment_note' => 'nullable|string|max:500']);
        $r->update($data + ['status' => 'paid_reported', 'paid_reported_at' => now()]);
        $this->tellOwners('paid', $user, $r->phone, "Paid with: {$data['payment_method']}\nReference: {$data['payment_reference']}" . (($data['payment_note'] ?? null) ? "\nNote: {$data['payment_note']}" : ''));
        AuditLog::record($user, 'billing.paid_reported', $r);

        return response()->json(['request' => self::present($r->fresh()), 'message' => 'Thank you. We will check your payment and turn Pro on, usually the same day.']);
    }

    // ---- the owner's side ----

    public function adminIndex(Request $request): JsonResponse
    {
        $q = UpgradeRequest::with('user:id,name,email,phone,subscription_tier,subscription_expires_at')->orderByRaw("case status when 'paid_reported' then 0 when 'requested' then 1 else 2 end")->orderByDesc('id');
        if ($request->query('status') === 'open') {
            $q->whereIn('status', ['requested', 'paid_reported']);
        }
        $rows = $q->limit(200)->get()->map(fn ($r) => self::present($r) + ['phone' => $r->phone, 'note' => $r->note, 'payment_note' => $r->payment_note, 'user' => ['id' => $r->user_id, 'name' => $r->user?->name, 'email' => $r->user?->email, 'is_pro' => (bool) $r->user?->isPro()]]);

        return response()->json(['data' => $rows->all(), 'open' => UpgradeRequest::whereIn('status', ['requested', 'paid_reported'])->count(), 'default_days' => config('kindo.payment.default_days')]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $r = UpgradeRequest::with('user')->findOrFail($id);
        if (! $r->isOpen()) {
            return response()->json(['message' => 'That request was already handled.'], 422);
        }
        $data = $request->validate(['days' => 'required|integer|between:1,3660', 'admin_note' => 'nullable|string|max:500']);
        $sub = $this->billing->extend($r->user, $data['days'], 'admin', null, 'Paid upgrade request #' . $r->id, $request->user());
        $r->update(['status' => 'approved', 'days_granted' => $data['days'], 'admin_note' => $data['admin_note'] ?? null, 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        $r->user->children()->get()->each->touch();   // the apps pick up the new plan through sync
        try {
            Mail::to($r->user->email)->send(new ProApprovedMail($r->user->name ?: 'there', $sub->ends_at->format('j F Y')));
        } catch (\Throwable $e) {
            Log::error('Pro approved email failed: ' . $e->getMessage());
        }
        AuditLog::record($request->user(), 'admin.upgrade_approved', $r, ['days' => $data['days'], 'user' => $r->user_id]);

        return response()->json(self::present($r->fresh()));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $r = UpgradeRequest::findOrFail($id);
        if (! $r->isOpen()) {
            return response()->json(['message' => 'That request was already handled.'], 422);
        }
        $data = $request->validate(['admin_note' => 'required|string|max:500']);
        $r->update(['status' => 'rejected', 'admin_note' => $data['admin_note'], 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        AuditLog::record($request->user(), 'admin.upgrade_rejected', $r);

        return response()->json(self::present($r->fresh()));
    }
}
