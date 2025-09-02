<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOrder extends Model
{
    protected $table = 'payment_orders';

    protected $fillable = [
        'user_id',
        'inviter_id',
        'mch_order_no',
        'order_reference',
        'remote_order_id',
        'remote_order_status',
        'product_key',
        'product_name', 
        'product_value',
        'product_type',
        'product_price',
        'receive_amount',
        'is_success',
        'is_finished',
        'order_success_response',
        'order_notify_response',
        'source',
        'payment_method'
    ];

    protected $casts = [
        'is_success' => 'boolean',
        'is_finished' => 'boolean',
        'order_success_response' => 'array',
        'order_notify_response' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}