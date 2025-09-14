<?php

namespace App\Http\Controllers;

use App\Services\KPIService;
use Illuminate\Http\Request;
use App\Services\LeaderboardService;

class MetricsController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date'); 

        $kpi = KPIService::getDaily($date);

        $topCustomers = LeaderboardService::top(10);

        $leaderboard = array_map(function($score, $customerId) {
            return [
                'customer_id' => $customerId,
                'revenue' => round($score, 2),
            ];
        }, array_values($topCustomers), array_keys($topCustomers));

        return response()->json([
            'date'        => $date ?? now()->toDateString(),
            'kpi'         => $kpi,
            'leaderboard' => $leaderboard,
        ]);
    }
}
