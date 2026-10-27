<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Messages from the platform owner. Parents see live ones they have not dismissed; the super admin manages them. */
class AnnouncementController extends Controller
{
    private function present(Announcement $a): array
    {
        return ['id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'level' => $a->level, 'starts_at' => $a->starts_at, 'ends_at' => $a->ends_at, 'active' => $a->active, 'created_at' => $a->created_at];
    }

    /** What the signed-in person should see right now. */
    public function mine(Request $request): JsonResponse
    {
        $seen = DB::table('announcement_dismissals')->where('user_id', $request->user()->id)->pluck('announcement_id');
        $rows = Announcement::live()->whereNotIn('id', $seen)->orderByRaw("case level when 'important' then 0 else 1 end")->orderByDesc('id')->limit(5)->get();

        return response()->json($rows->map(fn ($a) => $this->present($a))->all());
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        Announcement::findOrFail($id);
        DB::table('announcement_dismissals')->insertOrIgnore(['announcement_id' => $id, 'user_id' => $request->user()->id, 'created_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function index(): JsonResponse
    {
        return response()->json(Announcement::orderByDesc('id')->limit(100)->get()->map(function ($a) {
            return $this->present($a) + ['live' => Announcement::live()->whereKey($a->id)->exists(), 'dismissed_by' => DB::table('announcement_dismissals')->where('announcement_id', $a->id)->count()];
        })->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:120', 'body' => 'required|string|max:2000', 'level' => 'sometimes|in:info,important',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
        ]);
        $a = Announcement::create($data + ['level' => 'info', 'active' => true, 'created_by' => $request->user()->id]);
        AuditLog::record($request->user(), 'admin.announcement_created', $a, ['title' => $a->title]);

        return response()->json($this->present($a), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $a = Announcement::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:120', 'body' => 'sometimes|string|max:2000', 'level' => 'sometimes|in:info,important',
            'starts_at' => 'sometimes|nullable|date', 'ends_at' => 'sometimes|nullable|date', 'active' => 'sometimes|boolean',
        ]);
        $starts = array_key_exists('starts_at', $data) ? ($data['starts_at'] ? \Illuminate\Support\Carbon::parse($data['starts_at']) : null) : $a->starts_at;
        $ends = array_key_exists('ends_at', $data) ? ($data['ends_at'] ? \Illuminate\Support\Carbon::parse($data['ends_at']) : null) : $a->ends_at;
        if ($starts && $ends && $ends->lte($starts)) {
            return response()->json(['message' => 'The end must be after the start.', 'errors' => ['ends_at' => ['The end must be after the start.']]], 422);
        }
        $a->update($data);
        AuditLog::record($request->user(), 'admin.announcement_updated', $a, array_keys($data));

        return response()->json($this->present($a->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $a = Announcement::findOrFail($id);
        $a->delete();
        AuditLog::record($request->user(), 'admin.announcement_removed', null, ['title' => $a->title]);

        return response()->json(['ok' => true]);
    }
}
