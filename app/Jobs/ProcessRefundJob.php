<?php

namespace App\Jobs;

use Exception;
use App\Models\Order;
use App\Models\Refund;
use App\Services\KPIService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\LeaderboardService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $idempotencyKey;
    protected int $orderId;
    protected float $amount;

    public function __construct(string $idempotencyKey, int $orderId, float $amount)
    {
        $this->idempotencyKey = $idempotencyKey;
        $this->orderId = $orderId;
        $this->amount = $amount;
    }

    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $order = Order::find($this->orderId);

            if (!$order) {
                Log::error("Refund failed: Order not found", [
                    'order_id' => $this->orderId
                ]);
                DB::rollBack();
                return;
            }

            $refund = Refund::where('idempotency_key', $this->idempotencyKey)->first();

            if ($refund) {
                Log::info("Refund skipped - already processed", [
                    'refund_id' => $refund->id,
                    'idempotency_key' => $this->idempotencyKey
                ]);
                DB::rollBack();
                return;
            }

            $refund = Refund::create([
                'idempotency_key' => $this->idempotencyKey,
                'order_id' => $order->id,
                'amount' => $this->amount,
                'status' => 'processing',
            ]);

            if ($this->amount <= 0 || $this->amount > $order->total) {
                $refund->update(['status' => 'failed']);
                DB::commit();
                Log::error("Refund failed: Invalid amount", [
                    'order_id' => $order->id,
                    'amount' => $this->amount
                ]);
                return;
            }

            KPIService::decrementDaily($this->amount);
            LeaderboardService::subtractRevenue($order->customer_id, $this->amount);

            $refund->update(['status' => 'completed']);

            if ($this->amount == $order->total) {
                $order->status = 'refunded';
                $order->save();
            }

            DB::commit();

            Log::info("Refund processed successfully", [
                'order_id' => $order->id,
                'refund_id' => $refund->id,
                'amount' => $this->amount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error("Refund processing failed", [
                'order_id' => $this->orderId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
