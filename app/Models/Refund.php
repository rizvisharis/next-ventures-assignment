<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'order_id',
        'amount',
        'status',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
