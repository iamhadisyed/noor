<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AacCard;
use App\Services\ContentFilter;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A parent's own picture cards (a favourite toy, a school bag, a cousin's name). The platform's stock cards are untouched.
 * Pictures and voices are private: re-encoded, stored off the web root, served by signed link. Custom cards are never emergency cards.
 */
class AacCardController extends Controller
{
    public const CATEGORIES = ['needs', 'food', 'emotions', 'activities'];

    public function __construct(private MediaStore $media) {}

    /** Stock cards keep their old shape (icon_path is a public asset); a parent's cards expose signed links instead of paths. */
    public static function present(AacCard $c, MediaStore $media): array
    {
        $custom = $c->isCustom();

        return [
            'id' => $c->id, 'child_id' => $c->child_id, 'category' => $c->category, 'english_label' => $c->english_label, 'urdu_label' => $c->urdu_label,
            'icon_path' => $custom ? null : $c->icon_path, 'audio_path' => $custom ? null : $c->audio_path,
            'icon_url' => $custom ? $media->aacUrl($c, 'icon') : null, 'audio_url' => $custom ? $media->aacUrl($c, 'audio') : null,
            'icon_v' => $custom ? MediaStore::version($c->icon_path) : null, 'audio_v' => $custom ? MediaStore::version($c->audio_path) : null,
            'is_emergency' => $c->is_emergency, 'is_pro' => $c->is_pro, 'custom' => $custom, 'updated_at' => $c->updated_at,
        ];
    }

    private function owned(Request $request, int $id): AacCard
    {
        return AacCard::whereIn('child_id', $request->user()->accessibleChildren()->select('id'))->findOrFail($id);
    }

    private function rules(bool $partial): array
    {
        $r = $partial ? 'sometimes|' : 'required|';

        return [
            'english_label' => $r.'string|max:100',
            'urdu_label' => 'nullable|string|max:100',
            'category' => $r.'in:'.implode(',', self::CATEGORIES),
            'icon' => $r.'file|max:'.MediaStore::MAX_IMAGE_UPLOAD_KB,
            'audio' => 'sometimes|file|max:'.MediaStore::MAX_AUDIO_KB,
        ];
    }

    /** A card a child will tap should never carry a blocked word, so this is refused up front rather than flagged afterwards. */
    private function clean(array $data): ?JsonResponse
    {
        foreach (['english_label', 'urdu_label'] as $f) {
            if (ContentFilter::find($data[$f] ?? null)) {
                return response()->json(['message' => 'Please choose a different word for this card.', 'errors' => [$f => ['Please choose a different word for this card.']]], 422);
            }
        }

        return null;
    }

    public function store(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $max = (int) config('kindo.free_limits.custom_cards_per_child');
        if (! $request->user()->hasProAccess() && $child->aacCards()->count() >= $max) {
            return response()->json(['message' => "The free plan allows {$max} of your own cards per child.", 'upgrade' => true], 402);
        }

        $data = $request->validate($this->rules(false));
        if ($bad = $this->clean($data)) {
            return $bad;
        }

        $card = new AacCard(['child_id' => $child->id, 'category' => $data['category'], 'english_label' => trim($data['english_label']),
            'urdu_label' => trim($data['urdu_label'] ?? '') ?: trim($data['english_label']), 'is_emergency' => false, 'is_pro' => false, 'icon_path' => '']);
        try {
            $this->media->storeAacIcon($card, $request->file('icon'));
            if ($request->hasFile('audio')) {
                $this->media->storeAacAudio($card, $request->file('audio'));
            }
        } catch (\InvalidArgumentException $e) {
            $this->media->forgetAac($card);   // the picture may already be stored

            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        $card->save();

        return response()->json(self::present($card, $this->media), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $card = $this->owned($request, $id);
        $data = $request->validate($this->rules(true));
        if ($bad = $this->clean($data)) {
            return $bad;
        }

        $card->fill(collect($data)->only(['english_label', 'urdu_label', 'category'])->map(fn ($v) => is_string($v) ? trim($v) : $v)->all());
        if (array_key_exists('urdu_label', $data) && ! trim((string) $data['urdu_label'])) {
            $card->urdu_label = $card->english_label;
        }
        try {
            // check both files before touching the stored ones, so a bad upload never costs the parent the good one
            if ($request->hasFile('icon')) { $this->media->encodeJpeg($request->file('icon')->getRealPath()); }
            if ($request->hasFile('audio')) { $this->media->audioKind($request->file('audio')->getRealPath()); }
            if ($request->hasFile('icon')) { $this->media->storeAacIcon($card, $request->file('icon')); }
            if ($request->hasFile('audio')) { $this->media->storeAacAudio($card, $request->file('audio')); }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        $card->save();

        return response()->json(self::present($card->fresh(), $this->media));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $card = $this->owned($request, $id);
        $this->media->forgetAac($card);
        $card->delete();

        return response()->json(['ok' => true]);
    }
}
