<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ImportOrderCsvChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $header, $rows;

    public function __construct(array $header, array $rows)
    {
        $this->header = $header;
        $this->rows = $rows;
    }

    public function handle()
    {
        foreach ($this->rows as $row) {
            $data = array_combine($this->header, $row);

            if (!$data || empty($data['order_id']) || empty($data['customer_id'])) {
                Log::warning('Invalid CSV row skipped', $data ?? []);
                continue;
            }

            $customer = Customer::firstOrCreate(
                ['id' => $data['customer_id']],
                ['name' => $data['customer_name'] ?? 'Unknown']
            );

            $order = Order::updateOrCreate(
                ['order_id' => $data['order_id']],
                [
                    'customer_id' => $customer->id,
                    'total' => $data['total'],
                    'status' => 'pending',
                    'items' => $data['items']
                ]
            );

            ProcessOrderWorkflowJob::dispatch($order->id);
        }
    }
}
