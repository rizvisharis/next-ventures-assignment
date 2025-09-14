<?php

namespace App\Jobs;

use Exception;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use App\Models\OrderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderProcessedNotification;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    protected $status;
    protected $reason = null;

    /**
     * Create a new job instance.
     *
     * @param int $orderId
     * @param string $status
     * @param string|null $reason
     */
    public function __construct(int $orderId, string $status = 'success', ?string $reason = null)
    {
        $this->orderId = $orderId;
        $this->status = $status;
        $this->reason = $reason;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $order = Order::with('customer')->find($this->orderId);

        if (!$order) {
            Log::warning("SendOrderNotificationJob: Order not found for ID {$this->orderId}");
            return;
        }

        $payload = [
            'order_id'    => $order->order_id,
            'customer_id' => $order->customer_id,
            'status'      => $this->status,
            'total'       => (float) $order->total,
            'reason'      => $this->reason,
        ];

        $channel = 'log';

        if (!empty($order->customer->email)) {
            try {
                Notification::route('mail', $order->customer->email)
                    ->notify(new OrderProcessedNotification($payload));
                $channel = 'email';
            } catch (Exception $e) {
                Log::error('Email notification failed', [
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ]);
            }
        } else {
            Log::info('Order processed - log notification', $payload);
        }

        OrderNotification::create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'channel'     => $channel,
            'status'      => $this->status,
            'total'       => $order->total,
            'payload'     => $payload,
        ]);
    }
}
