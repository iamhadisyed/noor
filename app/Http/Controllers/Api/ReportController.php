<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePediatricReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function generate(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        if (! $request->user()->isPro()) {
            return response()->json(['message' => 'AI diagnostics require Kindo Pro.', 'upgrade' => true], 402);
        }

        GeneratePediatricReportJob::dispatch($child->id);

        return response()->json(['queued' => true], 202);
    }

    public function latest(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $report = $child->reports()->latest('report_week_start')->first();

        return $report ? response()->json($report) : response()->json(['message' => 'No report yet.'], 404);
    }
}
