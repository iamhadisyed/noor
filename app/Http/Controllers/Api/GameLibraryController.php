<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GameItem;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The administrator's library of animals for the sound games: names, a picture, and the sound each one makes. */
class GameLibraryController extends Controller
{
    public function __construct(private MediaStore $media) {}

    private function present(GameItem $i): array
    {
        return ['id' => $i->id, 'slug' => $i->slug, 'name_en' => $i->name_en, 'name_ur' => $i->name_ur, 'active' => $i->active,
            'has_image' => (bool) $i->image_path, 'has_audio' => (bool) $i->audio_path, 'complete' => $i->isComplete(),
            'image_url' => $this->media->gameUrl($i, 'image'), 'audio_url' => $this->media->gameUrl($i, 'audio')];
    }

    public function index(): JsonResponse
    {
        $items = GameItem::orderBy('name_en')->get();

        return response()->json(['items' => $items->map(fn ($i) => $this->present($i))->all(), 'complete' => $items->filter->isComplete()->count(), 'needed' => (int) config('games.min_library_items')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name_en' => 'required|string|max:60', 'name_ur' => 'required|string|max:60']);
        $slug = Str::slug($data['name_en']) ?: 'animal';
        $i = 1;
        $base = $slug;
        while (GameItem::where('slug', $slug)->exists()) { $slug = $base . '-' . ++$i; }
        $item = GameItem::create($data + ['slug' => $slug, 'kind' => 'animal']);
        AuditLog::record($request->user(), 'admin.game_item_added', $item, ['slug' => $slug]);

        return response()->json($this->present($item), 201);
    }

    /** Change names, switch on or off, and upload or replace the picture and sound (multipart). */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = GameItem::findOrFail($id);
        $data = $request->validate([
            'name_en' => 'sometimes|string|max:60', 'name_ur' => 'sometimes|string|max:60', 'active' => 'sometimes|boolean',
            'image' => 'sometimes|file|max:' . MediaStore::MAX_IMAGE_UPLOAD_KB, 'audio' => 'sometimes|file|max:' . MediaStore::MAX_AUDIO_KB,
        ]);
        try {
            // check both before storing either, so a bad upload never replaces a good file
            if ($request->hasFile('image')) { $this->media->encodeJpeg($request->file('image')->getRealPath()); }
            if ($request->hasFile('audio')) { $this->media->audioKind($request->file('audio')->getRealPath()); }
            if ($request->hasFile('image')) { $this->media->storeGameImage($item, $request->file('image')); }
            if ($request->hasFile('audio')) { $this->media->storeGameAudio($item, $request->file('audio')); }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        $item->fill(array_intersect_key($data, array_flip(['name_en', 'name_ur', 'active'])))->save();
        AuditLog::record($request->user(), 'admin.game_item_updated', $item, ['fields' => array_keys($data)]);

        return response()->json($this->present($item->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = GameItem::findOrFail($id);
        $this->media->forgetGame($item);
        $item->delete();
        AuditLog::record($request->user(), 'admin.game_item_removed', null, ['slug' => $item->slug]);

        return response()->json(['ok' => true]);
    }
}
