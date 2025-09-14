<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    protected static function key(): string
    {
        return config('app.env') . ':leaderboard:customers';
    }

    
    public static function addRevenue(int $customerId, float $amount): void
    {
        Redis::connection()->zIncrBy(self::key(), $amount, (string)$customerId);
    }

    public static function subtractRevenue(int $customerId, float $amount): void
    {
        Redis::connection()->zIncrBy(self::key(), -$amount, (string)$customerId);
    }

    public static function top(int $limit = 10): array
    {
        return Redis::connection()->zRevRangeWithScores(self::key(), 0, $limit - 1);
    }
}
