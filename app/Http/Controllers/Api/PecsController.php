<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AacCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PecsController extends Controller
{
    public function download(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['size' => 'sometimes|in:a4,letter', 'ids' => 'sometimes|array', 'ids.*' => 'integer']);

        $query = AacCard::where(fn ($q) => $q->whereNull('child_id')->orWhereIn('child_id', $user->children()->select('id')));

        if ($user->isPro()) {
            if (! empty($data['ids'])) {
                $query->whereIn('id', $data['ids']);
            }
        } else {
            // Free tier: single sample sheet of the four core cards.
            $query->where('is_pro', false)->whereIn('english_label', ['Water', 'Potty', 'Milk', 'Bed'])->limit(4);
        }

        $size = $user->isPro() ? ($data['size'] ?? 'a4') : 'a4';

        return Pdf::loadView('pdf.pecs-printable', ['cards' => $query->get()])
            ->setPaper($size, 'portrait')
            ->download('kindo-pecs.pdf');
    }
}
