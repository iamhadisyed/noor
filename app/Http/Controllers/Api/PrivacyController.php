<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoginCodeMail;
use App\Models\AiDiagnosticReport;
use App\Models\AuditLog;
use App\Models\ChildNeedsLog;
use App\Models\ContentFlag;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Models\LoginCode;
use App\Models\PracticeSession;
use App\Services\AccountEraser;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** The parent's rights over their own data: a full copy, and complete deletion. */
class PrivacyController extends Controller
{
    private const BAD_CODE = 'That code is not valid. Check it or ask for a new one.';

    /** A zip with one readable data.json and every picture and voice the parent uploaded. */
    public function export(Request $request)
    {
        $user = $request->user();
        $kids = $user->children()->get();
        $ids = $kids->pluck('id');
        $disk = Storage::disk(MediaStore::DISK);

        $zipPath = tempnam(sys_get_temp_dir(), 'kindo-export');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Files are added by path, so a big account does not have to fit in memory.
        $addFile = function (?string $stored, string $name) use ($zip, $disk): ?string {
            if ($stored && $disk->exists($stored)) {
                $zip->addFile($disk->path($stored), $name);

                return $name;
            }

            return null;
        };

        $lessons = Lesson::where('is_template', false)->whereIn('child_id', $ids)->with('tasks')->orderBy('id')->get()->map(function ($l) use ($addFile) {
            $row = collect($l->toArray())->except(['tasks'])->all();
            $row['tasks'] = $l->tasks->map(function ($t) use ($addFile, $l) {
                $task = collect($t->toArray())->except(['image_path', 'audio_path'])->all();
                $task['picture_file'] = $addFile($t->image_path, "media/lessons/{$l->id}/task-{$t->id}-picture." . pathinfo((string) $t->image_path, PATHINFO_EXTENSION));
                $task['sound_file'] = $addFile($t->audio_path, "media/lessons/{$l->id}/task-{$t->id}-sound." . pathinfo((string) $t->audio_path, PATHINFO_EXTENSION));

                return $task;
            })->all();

            return $row;
        })->all();

        $family = FamilyMember::whereIn('child_id', $ids)->orderBy('id')->get()->map(function ($m) use ($addFile) {
            $row = $m->toArray();
            $row['photo_file'] = $addFile($m->photo_path, "media/family/{$m->id}-photo." . pathinfo((string) $m->photo_path, PATHINFO_EXTENSION));
            $row['voice_file'] = $addFile($m->audio_prompt_path, "media/family/{$m->id}-voice." . pathinfo((string) $m->audio_prompt_path, PATHINFO_EXTENSION));

            return $row;
        })->all();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'about' => 'Everything Kindo holds about this account. Pictures and voices are in the media folder and named in the entries below.',
            'account' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'plan' => $user->isPro() ? 'pro' : 'free', 'created_at' => $user->created_at, 'last_login_at' => $user->last_login_at],
            'consent' => ['terms_version' => $user->terms_version, 'terms_accepted_at' => $user->terms_accepted_at],
            'children' => $kids->toArray(),
            'lessons' => $lessons,
            'family_cards' => $family,
            'alerts' => ChildNeedsLog::whereIn('child_id', $ids)->orderBy('id')->get()->toArray(),
            'practice_sessions' => PracticeSession::whereIn('child_id', $ids)->with('mistakes', 'letters')->orderBy('id')->get()->toArray(),
            'ai_reports' => AiDiagnosticReport::whereIn('child_id', $ids)->orderBy('id')->get()->toArray(),
            'moderation_flags' => ContentFlag::where('user_id', $user->id)->orderBy('id')->get(['id', 'reason', 'term', 'excerpt', 'status', 'action', 'created_at', 'reviewed_at'])->toArray(),
            'activity_log' => AuditLog::where('actor_id', $user->id)->orderBy('id')->get(['action', 'created_at', 'ip'])->toArray(),
        ];
        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();

        AuditLog::record($user, 'account.exported');

        return response()->download($zipPath, 'kindo-my-data-' . now()->format('Y-m-d') . '.zip', ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    /** Step 1 of deleting the account: a code goes to the account's email. */
    public function requestDeleteCode(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'An administrator account cannot be deleted here.'], 422);
        }
        $cooldown = (int) config('kindo.otp.cooldown_seconds');
        $last = LoginCode::where('email', $user->email)->where('purpose', 'delete')->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds($cooldown))) {
            return response()->json(['message' => 'Please wait before asking for another code.'], 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('kindo.otp.ttl_minutes');
        LoginCode::create(['email' => $user->email, 'purpose' => 'delete', 'code_hash' => LoginCode::hash($code), 'expires_at' => now()->addMinutes($minutes), 'ip' => $request->ip()]);
        try {
            Mail::to($user->email)->send(new LoginCodeMail($code, $minutes, 'delete'));
        } catch (\Throwable $e) {
            Log::error('Delete-account email failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'A confirmation code is on its way to your email.'], 202);
    }

    /** Step 2: the code, and everything is gone. */
    public function destroy(Request $request, AccountEraser $eraser): JsonResponse
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'An administrator account cannot be deleted here.'], 422);
        }
        $data = $request->validate(['code' => 'required|digits:6']);

        $code = LoginCode::where('email', $user->email)->where('purpose', 'delete')->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $code) {
            return response()->json(['message' => self::BAD_CODE], 422);
        }
        if (! hash_equals($code->code_hash, LoginCode::hash($data['code']))) {
            $code->increment('attempts');
            if ($code->attempts >= (int) config('kindo.otp.max_attempts')) {
                $code->update(['consumed_at' => now()]);
            }

            return response()->json(['message' => self::BAD_CODE], 422);
        }

        $eraser->erase($user);

        return response()->json(['ok' => true]);
    }
}
