<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Vouchers and manual grants. The super admin sees who used what. */
class AdminBillingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    private function present(Voucher $v): array
    {
        return ['id' => $v->id, 'code' => $v->display(), 'days' => $v->days, 'note' => $v->note, 'status' => $v->status(), 'expires_at' => $v->expires_at,
            'used_by' => $v->user?->email, 'used_at' => $v->used_at, 'created_at' => $v->created_at];
    }

    public function vouchers(Request $request): JsonResponse
    {
        $q = Voucher::with('user:id,email')->orderByDesc('id');
        $status = $request->query('status');
        if ($status === 'unused') { $q->whereNull('used_at')->whereNull('revoked_at')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now())); }
        elseif ($status === 'used') { $q->whereNotNull('used_at'); }
        $page = $q->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn ($v) => $this->present($v))->all(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'counts' => ['unused' => Voucher::whereNull('used_at')->whereNull('revoked_at')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(), 'used' => Voucher::whereNotNull('used_at')->count()],
        ]);
    }

    public function createVouchers(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => 'required|integer|between:1,3660', 'count' => 'required|integer|between:1,100', 'note' => 'nullable|string|max:120', 'expires_at' => 'nullable|date|after:now']);
        $made = $this->billing->makeVouchers($data['count'], $data['days'], $data['note'] ?? null, isset($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at']) : null, $request->user());
        AuditLog::record($request->user(), 'admin.vouchers_created', null, ['count' => count($made), 'days' => $data['days']]);

        return response()->json(['vouchers' => array_map(fn ($v) => $this->present($v), $made)], 201);
    }

    /** Cancel a code that has not been used. */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $v = Voucher::findOrFail($id);
        if ($v->used_at) {
            return response()->json(['message' => 'That code has already been used.'], 422);
        }
        $v->update(['revoked_at' => now()]);
        AuditLog::record($request->user(), 'admin.voucher_revoked', $v);

        return response()->json($this->present($v->fresh()));
    }

    /** Give a parent Pro time by hand. */
    public function grant(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $data = $request->validate(['days' => 'required|integer|between:1,3660', 'note' => 'nullable|string|max:120']);
        $sub = $this->billing->extend($user, $data['days'], 'admin', null, $data['note'] ?? null, $request->user());
        AuditLog::record($request->user(), 'admin.pro_granted', $user, ['days' => $data['days']]);

        return response()->json(['plan' => 'pro', 'expires_at' => $sub->ends_at]);
    }
}
