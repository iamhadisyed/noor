<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pro time is a ledger of periods added to a person's expiry. A new period starts when the current one ends, so
 * redeeming early never wastes days. Vouchers are one-time codes; the app stores will add a third source later.
 */
class BillingService
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // no 0/O, 1/I/L

    /** Strip spaces and dashes, upper-case. */
    public static function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /** @return list<Voucher> */
    public function makeVouchers(int $count, int $days, ?string $note, ?\DateTimeInterface $expires, ?User $by): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = Voucher::create(['code' => $this->newCode(), 'days' => $days, 'note' => $note, 'expires_at' => $expires, 'created_by' => $by?->id]);
        }

        return $out;
    }

    /** Add days of Pro, starting when the current period ends (or now, if there is none). */
    public function extend(User $user, int $days, string $source, ?Voucher $voucher = null, ?string $note = null, ?User $by = null): Subscription
    {
        return DB::transaction(function () use ($user, $days, $source, $voucher, $note, $by) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $start = $user->isPro() && $user->subscription_expires_at ? $user->subscription_expires_at : now();
            $end = $start->copy()->addDays($days);
            $user->update(['subscription_tier' => 'pro', 'subscription_expires_at' => $end]);

            return Subscription::create(['user_id' => $user->id, 'source' => $source, 'days' => $days, 'starts_at' => $start, 'ends_at' => $end, 'voucher_id' => $voucher?->id, 'note' => $note, 'created_by' => $by?->id]);
        });
    }

    /** Redeem a code. Returns the new period, or null for any reason it cannot be used (the caller gives one neutral message). */
    public function redeem(User $user, string $code): ?Subscription
    {
        $code = self::normalise($code);
        if (strlen($code) !== 8) {
            return null;
        }

        return DB::transaction(function () use ($user, $code) {
            $v = Voucher::where('code', $code)->lockForUpdate()->first();
            if (! $v || $v->status() !== 'unused') {
                return null;
            }
            // claim it first: two people racing for the same code cannot both win
            $claimed = Voucher::whereKey($v->id)->whereNull('used_by')->update(['used_by' => $user->id, 'used_at' => now()]);
            if (! $claimed) {
                return null;
            }

            return $this->extend($user, $v->days, 'voucher', $v->fresh(), $v->note);
        });
    }

    /** @return array{plan: string, expires_at: ?string, days_left: ?int, via_household: bool, history: list<array>} */
    public function summary(User $user): array
    {
        $own = $user->isPro();
        $shared = ! $own && $user->hasProAccess();
        $left = $own && $user->subscription_expires_at ? (int) ceil(now()->floatDiffInDays($user->subscription_expires_at)) : null;

        return [
            'plan' => ($own || $shared) ? 'pro' : 'free',
            'expires_at' => $own ? $user->subscription_expires_at?->toIso8601String() : null,
            'days_left' => $left,
            'via_household' => $shared,
            'history' => Subscription::where('user_id', $user->id)->orderByDesc('id')->limit(20)->get()->map(fn ($s) => [
                'source' => $s->source, 'days' => $s->days, 'starts_at' => $s->starts_at, 'ends_at' => $s->ends_at, 'note' => $s->note,
            ])->all(),
        ];
    }
}
