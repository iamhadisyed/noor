<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Services\ContentFilter;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Family cards for the matching game. Photos and voices are private: re-encoded, stored off the web root, served by signed link. */
class FamilyMemberController extends Controller
{
    public function __construct(private MediaStore $media) {}

    public static function present(FamilyMember $m, MediaStore $media): array
    {
        return [
            'id' => $m->id, 'child_id' => $m->child_id, 'relation_title' => $m->relation_title, 'display_name' => $m->display_name,
            'photo_url' => $media->familyUrl($m, 'photo'), 'audio_url' => $media->familyUrl($m, 'audio'), 'photo_v' => MediaStore::version($m->photo_path), 'audio_v' => MediaStore::version($m->audio_prompt_path),
            'consented_at' => $m->consented_at, 'created_at' => $m->created_at, 'updated_at' => $m->updated_at,
        ];
    }

    private function owned(Request $request, int $id): FamilyMember
    {
        return FamilyMember::whereIn('child_id', $request->user()->accessibleChildren()->select('id'))->findOrFail($id);
    }

    public function index(Request $request, int $childId): JsonResponse
    {
        $members = ChildController::owned($request, $childId)->familyMembers()->orderBy('id')->get();

        return response()->json($members->map(fn ($m) => self::present($m, $this->media))->all());
    }

    public function store(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        if (! $request->user()->hasProAccess() && $child->familyMembers()->count() >= config('kindo.free_limits.family_members')) {
            return response()->json(['message' => 'Free plan allows 2 family cards.', 'upgrade' => true], 402);
        }

        $data = $request->validate([
            'relation_title' => 'required|string|max:100',
            'display_name' => 'required|string|max:100',
            'photo' => 'required|file|max:' . MediaStore::MAX_IMAGE_UPLOAD_KB,
            'audio' => 'required|file|max:' . MediaStore::MAX_AUDIO_KB,
            // The parent confirms they may use this person's photo and voice, and that it is suitable for a child.
            'consent' => 'required|accepted',
        ], ['consent.accepted' => 'Please confirm you have permission to use this photo and voice.', 'consent.required' => 'Please confirm you have permission to use this photo and voice.']);

        $member = new FamilyMember(['child_id' => $child->id, 'relation_title' => $data['relation_title'], 'display_name' => $data['display_name'], 'consented_at' => now(), 'consented_by' => $request->user()->id]);
        try {
            $this->media->storeFamilyPhoto($member, $request->file('photo'));
            $this->media->storeFamilyAudio($member, $request->file('audio'));
        } catch (\InvalidArgumentException $e) {
            $this->media->forgetFamily($member);   // the first file may already be stored

            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        $member->save();

        app(ContentFilter::class)->scanFamilyMember($member, $request->user()->id);

        return response()->json(self::present($member, $this->media), 201);
    }

    /** Change the names, and optionally swap the photo or the voice. */
    public function update(Request $request, int $id): JsonResponse
    {
        $member = $this->owned($request, $id);
        $data = $request->validate([
            'relation_title' => 'sometimes|string|max:100',
            'display_name' => 'sometimes|string|max:100',
            'photo' => 'sometimes|file|max:' . MediaStore::MAX_IMAGE_UPLOAD_KB,
            'audio' => 'sometimes|file|max:' . MediaStore::MAX_AUDIO_KB,
        ]);

        $member->fill(array_intersect_key($data, array_flip(['relation_title', 'display_name'])));
        try {
            // check both files before touching the stored ones, so a bad upload never costs the parent the good one
            if ($request->hasFile('photo')) { $this->media->encodeJpeg($request->file('photo')->getRealPath()); }
            if ($request->hasFile('audio')) { $this->media->audioKind($request->file('audio')->getRealPath()); }
            if ($request->hasFile('photo')) { $this->media->storeFamilyPhoto($member, $request->file('photo')); }
            if ($request->hasFile('audio')) { $this->media->storeFamilyAudio($member, $request->file('audio')); }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        $member->save();
        app(ContentFilter::class)->scanFamilyMember($member, $request->user()->id);

        return response()->json(self::present($member->fresh(), $this->media));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $member = $this->owned($request, $id);
        $this->media->forgetFamily($member);
        $member->delete();

        return response()->json(['ok' => true]);
    }
}
