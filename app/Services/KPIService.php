<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class KPIService
{
    protected static function getKey(?string $date = null): string
    {
        $date = $date ?? now()->toDateString(); 
        return config('app.env') . ":kpi:{$date}";
    }

    public static function incrementDaily(float $amount): void
    {
        $key = self::getKey();
        $redis = Redis::connection();

        $redis->hincrbyfloat($key, 'revenue', $amount);
        $redis->hincrby($key, 'order_count', 1);
    }

    
    public static function decrementDaily(float $amount): void
    {
        $key = self::getKey();
        $redis = Redis::connection();

        $redis->hincrbyfloat($key, 'revenue', -$amount);
        $redis->hincrby($key, 'order_count', -1);
    }

    public static function getDaily(?string $date = null): array
    {
        $key = self::getKey($date);
        $data = Redis::connection()->hgetall($key);

        $revenue = isset($data['revenue']) ? floatval($data['revenue']) : 0.0;
        $count   = isset($data['order_count']) ? intval($data['order_count']) : 0;
        $avg     = $count > 0 ? $revenue / $count : 0.0;

        return [
            'revenue'         => $revenue,
            'order_count'     => $count,
            'avg_order_value' => $avg,
        ];
    }
}
