<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderNotification extends Model
{
    use HasFactory;

    protected $table = 'order_notifications';

    protected $fillable = [
        'order_id',
        'customer_id',
        'channel',
        'status',
        'total',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'total' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
