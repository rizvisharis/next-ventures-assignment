<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\KPIService;
use Illuminate\Bus\Queueable;
use App\Services\LeaderboardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessOrderWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $order = Order::find($this->orderId);
        if (!$order) return;

        // Lock via Redis to avoid double processing
        $lockKey = "order:processing:{$this->orderId}";
        $lock = Cache::store('redis')->lock($lockKey, 30); 
        if (!$lock->get()) return;

        try {
            if (in_array($order->status, ['finalized', 'paid', 'rolled_back', 'failed'])) {
                return;
            }

            if (!$this->reserveStock()) {
                $order->update(['status' => 'failed']);
                SendOrderNotificationJob::dispatch($order->id, 'stock_failed');
                return;
            }
            $order->update(['status' => 'stock_reserved']);

            if (!$this->simulatePayment()) {
                $order->update(['status' => 'failed']);
                SendOrderNotificationJob::dispatch($order->id, 'payment_failed', 'Payment gateway declined');
                return;
            }
            $order->update(['status' => 'payment_simulated']);

            $order->update(['status' => 'completed']);
            SendOrderNotificationJob::dispatch($order->id, 'success');
            
            KPIService::incrementDaily($order->total);
            LeaderboardService::addRevenue($order->customer_id, $order->total);
        } finally {
            $lock->release();
        }
    }

    private function reserveStock(): bool
    {
        return rand(1, 10) > 1; // 90% success
    }

    private function simulatePayment(): bool
    {
        return rand(1, 10) > 2; // 80% success
    }
}
