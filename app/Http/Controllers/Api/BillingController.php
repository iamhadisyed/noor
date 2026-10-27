<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /** The plan, when it ends, and every period of Pro so far. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->billing->summary($request->user()) + [
            'limits' => ['free' => ['children' => (int) config('kindo.free_limits.children'), 'lessons_per_child' => (int) config('kindo.free_limits.lessons_per_child'), 'family_cards' => (int) config('kindo.free_limits.family_members')],
                'pro' => ['children' => (int) config('kindo.free_limits.pro_children'), 'lessons_per_child' => null, 'family_cards' => null]],
            'store_purchase' => false,   // buying inside the apps is not switched on yet; vouchers are
        ]);
    }

    public function redeem(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = $request->validate(['code' => 'required|string|max:32'])['code'];

        if ($user->isPro() && ! $user->subscription_expires_at) {
            return response()->json(['message' => 'Your Pro plan has no end date, so there is nothing to add.'], 422);
        }
        // one neutral message for every reason, so codes cannot be probed
        $sub = $this->billing->redeem($user, $code);
        if (! $sub) {
            AuditLog::record($user, 'billing.redeem_failed');

            return response()->json(['message' => 'That code is not valid, has already been used, or has expired.', 'errors' => ['code' => ['That code is not valid, has already been used, or has expired.']]], 422);
        }
        AuditLog::record($user, 'billing.redeemed', $user, ['days' => $sub->days]);

        return response()->json($this->billing->summary($user->fresh()));
    }
}
