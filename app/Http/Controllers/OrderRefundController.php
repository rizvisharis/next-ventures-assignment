<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\ProcessRefundJob;

class OrderRefundController extends Controller
{
    public function refund(Request $request, int $orderId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key') ?? Str::uuid()->toString();

        ProcessRefundJob::dispatch($idempotencyKey, $orderId, $request->amount);

        return response()->json([
            'message' => 'Refund request queued',
            'idempotency_key' => $idempotencyKey
        ]);
    }
}
