<?php

namespace App\Http\Controllers;

use App\Services\VisitorStatistics;
use Illuminate\Http\JsonResponse;

class VisitorStatisticsController extends Controller
{
    public function __invoke(VisitorStatistics $statistics): JsonResponse
    {
        return response()->json($statistics->summary())->header('Cache-Control', 'no-store');
    }
}
