<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoginCodeMail;
use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Passwordless sign-in: an emailed 6-digit code, single use, hashed at rest, limited attempts.
 */
class AuthController extends Controller
{
    private const NEUTRAL = 'If that email can sign in, a code is on its way.';
    private const BAD_CODE = 'That code is not valid. Check it or ask for a new one.';

    public function requestCode(Request $request): JsonResponse
    {
        $email = strtolower($request->validate(['email' => 'required|email:rfc|max:191'])['email']);
        $cooldown = (int) config('kindo.otp.cooldown_seconds');

        $last = LoginCode::where('email', $email)->where('purpose', 'login')->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds($cooldown))) {
            $wait = max(1, $cooldown - (int) $last->created_at->diffInSeconds(now()));

            return response()->json(['message' => 'Please wait before asking for another code.', 'retry_after' => $wait], 429)
                ->header('Retry-After', (string) $wait);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('kindo.otp.ttl_minutes');

        LoginCode::create([
            'email' => $email,
            'code_hash' => LoginCode::hash($code),
            'expires_at' => now()->addMinutes($minutes),
            'ip' => $request->ip(),
        ]);

        try {
            Mail::to($email)->send(new LoginCodeMail($code, $minutes));
        } catch (\Throwable $e) {
            Log::error('Sign-in email failed: ' . $e->getMessage());   // same answer to the caller; ops see it in the log
        }

        return response()->json(['message' => self::NEUTRAL], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:191',
            'code' => 'required|digits:6',
            'device_name' => 'nullable|string|max:60',
            'accept_terms' => 'nullable|boolean',
        ]);
        $email = strtolower($data['email']);

        $code = LoginCode::where('email', $email)->where('purpose', 'login')->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $code) {
            return response()->json(['message' => self::BAD_CODE], 422);
        }

        if (! hash_equals($code->code_hash, LoginCode::hash($data['code']))) {
            $code->increment('attempts');
            if ($code->attempts >= (int) config('kindo.otp.max_attempts')) {
                $code->update(['consumed_at' => now()]);          // too many wrong tries: this code is dead
            }

            return response()->json(['message' => self::BAD_CODE], 422);
        }

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;
        $acceptedNow = (bool) ($data['accept_terms'] ?? false);
        if ($isNew && ! $acceptedNow && ! in_array($email, config('kindo.super_admins', []), true)) {
            // The code is still valid: the client shows the terms, then sends the same code again with accept_terms.
            return response()->json(['message' => 'Please read and accept the terms to create your account.', 'terms_required' => true, 'terms_version' => config('legal.version')], 422);
        }
        $code->update(['consumed_at' => now()]);
        if ($isNew) {
            $user->name = Str::before($email, '@');
        }
        if (! $user->isActive() && ! $isNew) {
            return response()->json(['message' => 'This account is unavailable.'], 403);
        }

        $user->role = in_array($email, config('kindo.super_admins', []), true) ? 'super_admin' : 'parent';   // configuration is the source of truth
        $user->email_verified_at ??= now();
        if ($acceptedNow) {
            $user->terms_accepted_at = now();
            $user->terms_version = config('legal.version');
        }
        $user->last_login_at = now();
        $user->save();

        $expires = $user->isSuperAdmin() ? now()->addHours((int) config('kindo.admin_token_ttl_hours')) : now()->addDays((int) config('kindo.token_ttl_days'));
        $token = $user->createToken($data['device_name'] ?? 'web', ['*'], $expires);
        AuditLog::record($user, 'auth.login', $user, ['device' => $data['device_name'] ?? 'web', 'new_account' => $isNew]);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expires->toIso8601String(),
            'is_new' => $isNew,
            'user' => $this->present($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->present($request->user()));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30']);
        $request->user()->update($data);

        return response()->json($this->present($request->user()->fresh()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        AuditLog::record($request->user(), 'auth.logout', $request->user());

        return response()->json(['ok' => true]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        return response()->json($request->user()->tokens()->latest('id')->get()->map(fn ($t) => [
            'id' => $t->id, 'device' => $t->name, 'created_at' => $t->created_at, 'last_used_at' => $t->last_used_at,
            'expires_at' => $t->expires_at, 'current' => $t->id === $currentId,
        ]));
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->where('id', $id)->firstOrFail()->delete();
        AuditLog::record($request->user(), 'auth.session_revoked', $request->user(), ['token_id' => $id]);

        return response()->json(['ok' => true]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:500', 'device_type' => 'required|in:android,ios']);

        DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'device_type' => $data['device_type']]);

        return response()->json(['ok' => true]);
    }

    private function present(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'role' => $u->role,
            'plan' => $u->isPro() ? 'pro' : 'free', 'email_verified' => (bool) $u->email_verified_at,
            'terms_required' => ! $u->isSuperAdmin() && $u->terms_version !== config('legal.version'), 'terms_version' => config('legal.version')];
    }

    /** The public terms and privacy text (also shown before sign-up). */
    public function terms(): JsonResponse
    {
        return response()->json(config('legal'));
    }

    /** An existing parent accepts a newer version of the terms. */
    public function acceptTerms(Request $request): JsonResponse
    {
        $request->validate(['accept' => 'required|accepted']);
        $request->user()->update(['terms_accepted_at' => now(), 'terms_version' => config('legal.version')]);
        AuditLog::record($request->user(), 'auth.terms_accepted', $request->user(), ['version' => config('legal.version')]);

        return response()->json($this->present($request->user()->fresh()));
    }
}
