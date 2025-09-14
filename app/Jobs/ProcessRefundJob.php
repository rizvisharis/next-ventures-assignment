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
                Log::error("Refund failed: Order not found", ['order_id' => $this->orderId]);
                DB::rollBack();
                return;
            }

            $refund = Refund::createOrFirst(
                [
                    'idempotency_key' => $this->idempotencyKey,
                ],
                [
                    'order_id' => $order->id,
                    'amount' => $this->amount,
                    'status' => 'processing',
                    'refund_type' => null,
                ]
            );

            if (!$refund->wasRecentlyCreated) {
                if ($refund->status === 'completed') {
                    Log::info("Refund skipped - already completed", [
                        'refund_id' => $refund->id,
                        'idempotency_key' => $this->idempotencyKey,
                    ]);
                    DB::commit();
                    return;
                }

                if ($refund->status === 'failed') {
                    Log::warning("Refund skipped - previously failed", [
                        'refund_id' => $refund->id,
                        'idempotency_key' => $this->idempotencyKey,
                    ]);
                    DB::commit();
                    return;
                }
            }

            $totalRefunded = Refund::where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('amount');

            $remainingAmount = $order->total - $totalRefunded;

            if ($this->amount > $remainingAmount) {
                $refund->update([
                    'status' => 'failed',
                    'refund_type' => null,
                ]);

                Log::error("Refund failed: Over refund attempt", [
                    'order_id' => $order->id,
                    'requested_amount' => $this->amount,
                    'remaining_amount' => $remainingAmount,
                    'already_refunded' => $totalRefunded,
                ]);

                DB::commit();
                return;
            }

            $refundType = ($this->amount == $remainingAmount) ? 'full' : 'partial';

            $refund->update([
                'status' => 'completed',
                'refund_type' => $refundType,
            ]);

            KPIService::decrementDaily($this->amount);
            LeaderboardService::subtractRevenue($order->customer_id, $this->amount);

            if ($refundType === 'full') {
                $order->status = 'refunded';
                $order->save();
            }

            DB::commit();

            Log::info("Refund processed successfully", [
                'order_id' => $order->id,
                'refund_id' => $refund->id,
                'amount' => $this->amount,
                'refund_type' => $refundType,
                'total_refunded_now' => $totalRefunded + $this->amount,
                'remaining' => $remainingAmount - $this->amount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error("Refund processing failed unexpectedly", [
                'order_id' => $this->orderId,
                'idempotency_key' => $this->idempotencyKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
