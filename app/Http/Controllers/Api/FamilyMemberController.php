<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function index(Request $request, int $childId): JsonResponse
    {
        return response()->json(ChildController::owned($request, $childId)->familyMembers()->get());
    }

    public function store(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        if (! $request->user()->isPro() && $child->familyMembers()->count() >= config('kindo.free_limits.family_members')) {
            return response()->json(['message' => 'Free plan allows 2 family cards.', 'upgrade' => true], 402);
        }

        $data = $request->validate([
            'relation_title' => 'required|string|max:100',
            'display_name' => 'required|string|max:100',
            'photo' => 'required|image|max:300',                 // client resizes to 600x600 JPEG
            'audio' => 'required|file|mimetypes:audio/mp4,audio/aac,audio/webm,audio/ogg,audio/mpeg,video/webm|max:150', // 150 KB
        ]);

        $member = $child->familyMembers()->create([
            'relation_title' => $data['relation_title'],
            'display_name' => $data['display_name'],
            'photo_path' => $request->file('photo')->store("family/{$child->id}", 'public'),
            'audio_prompt_path' => $request->file('audio')->store("family/{$child->id}", 'public'),
        ]);

        app(\App\Services\ContentFilter::class)->scanFamilyMember($member, $request->user()->id);

        return response()->json($member, 201);
    }
}
