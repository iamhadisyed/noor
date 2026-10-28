<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BragCard;
use App\Services\Milestones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MilestoneController extends Controller
{
    public function index(Request $request, int $childId, Milestones $milestones): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        return response()->json(['child' => $child->name, 'milestones' => $milestones->for($child)]);
    }

    /** The shareable picture for one milestone. Only milestones the child has really reached can be made. */
    public function card(Request $request, int $childId, string $key, Milestones $milestones, BragCard $card): Response
    {
        $child = ChildController::owned($request, $childId);
        $v = $request->validate(['lang' => 'sometimes|in:en,ur', 'name' => 'sometimes|boolean']);
        $milestone = $milestones->find($child, $key) ?? abort(404);
        $first = ($v['name'] ?? true) ? (preg_split('/\s+/u', trim($child->name))[0] ?? '') : null;

        return response($card->render($milestone, $first, $v['lang'] ?? 'en'), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'Content-Disposition' => 'inline; filename="kindo-milestone.png"']);
    }
}
