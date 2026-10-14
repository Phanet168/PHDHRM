<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaderRecommendation;
use App\Models\LeaderRecommendationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaderRecommendationItemController extends Controller
{
    public function store(Request $request, LeaderRecommendation $leaderRecommendation): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);

        $data = $request->validate(['content_km' => ['required', 'string', 'max:1000']]);

        $leaderRecommendation->items()->create([
            'content_km' => strip_tags($data['content_km']),
            'is_active' => true,
            'sort_order' => $leaderRecommendation->items()->count(),
        ]);

        return back()->with('status', 'បានបន្ថែមអនុសាសន៍ដោយជោគជ័យ។');
    }

    public function update(Request $request, LeaderRecommendation $leaderRecommendation, LeaderRecommendationItem $item): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);
        abort_unless($item->recommendation_id === $leaderRecommendation->id, 404);

        $data = $request->validate(['content_km' => ['required', 'string', 'max:1000']]);

        $item->update(['content_km' => strip_tags($data['content_km'])]);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ។');
    }

    public function destroy(LeaderRecommendation $leaderRecommendation, LeaderRecommendationItem $item): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);
        abort_unless($item->recommendation_id === $leaderRecommendation->id, 404);

        $item->delete();

        return back()->with('status', 'បានលុបដោយជោគជ័យ។');
    }

    public function toggle(LeaderRecommendation $leaderRecommendation, LeaderRecommendationItem $item): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);
        abort_unless($item->recommendation_id === $leaderRecommendation->id, 404);

        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាពដោយជោគជ័យ។');
    }

    public function reorder(Request $request, LeaderRecommendation $leaderRecommendation): JsonResponse
    {
        $this->authorize('update', $leaderRecommendation);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:leader_recommendation_items,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $row) {
            $item = LeaderRecommendationItem::find($row['id']);
            if ($item && $item->recommendation_id === $leaderRecommendation->id) {
                $item->update(['sort_order' => $row['sort_order']]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
