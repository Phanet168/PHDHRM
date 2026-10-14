<?php

namespace App\Http\Controllers;

use App\Models\LeaderRecommendation;

class LeaderRecommendationController extends Controller
{
    public function show(LeaderRecommendation $leaderRecommendation)
    {
        abort_unless(
            $leaderRecommendation->is_active
                && (! $leaderRecommendation->published_at || $leaderRecommendation->published_at->isPast()),
            404
        );

        $leaderRecommendation->load(['media', 'activeItems']);

        return view('leader-recommendations.show', ['recommendation' => $leaderRecommendation]);
    }
}
