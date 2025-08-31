<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VipController extends Controller
{
    /**
     * Get available VIP plans
     */
    public function plans()
    {
        $plans = [
            [
                'key' => 'monthly',
                'name' => '月卡',
                'price' => 29,
                'duration' => 1,
                'duration_unit' => '个月',
                'features' => ['解锁全站所有漫画'],
                'popular' => false
            ],
            [
                'key' => 'quarterly',
                'name' => '季卡',
                'price' => 69,
                'original_price' => 90,
                'duration' => 3,
                'duration_unit' => '个月',
                'features' => ['解锁全站所有漫画'],
                'popular' => true,
                'save_amount' => 90 - 69
            ],
            [
                'key' => 'yearly',
                'name' => '年卡',
                'price' => 239,
                'original_price' => 360,
                'duration' => 12,
                'duration_unit' => '个月',
                'features' => ['解锁全站所有漫画'],
                'popular' => false,
                'save_amount' => 360 - 239
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Create payment order for VIP purchase
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'plan_key' => 'required|in:monthly,quarterly,yearly',
            'payment_method' => 'required|in:alipay,wechat'
        ]);

        $productKey = $request->input('plan_key');
        $paymentMethod = $request->input('payment_method');
        $productsList = config('products.products');
        $user = auth()->user();

        if (!isset($productsList[$productKey])) {
            return response()->json([
                'success' => false,
                'message' => '产品不存在！请联系管理员'
            ], 400);
        }

        $product = $productsList[$productKey];

        // Get payment product ID based on payment method
        $productId = $paymentMethod === 'wechat'
            ? config('payment.mch.wechat_id')
            : config('payment.mch.alipay_id');

        // Create payment order
        $paymentOrder = \App\Models\PaymentOrder::create([
            'user_id' => $user->id,
            'remote_order_status' => -1,
            'product_key' => $productKey,
            'product_value' => $product['value'],
            'product_type' => $product['type'],
            'product_name' => $product['name'],
            'product_price' => $product['price'],
            'source' => 1,
            'payment_method' => $paymentMethod
        ]);

        $paymentOrder->order_reference = md5('pro_order_' . $paymentOrder->id);
        $paymentOrder->save();

        // Call payment gateway
        $client = new \App\Services\PaymentGateway\MchPaymentProvider();
        $ip = filter_var($request->ip(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '127.0.0.1' : $request->ip();

        $res = $client->callCreate(
            $product['decimal_price'],  // Use decimal_price for payment gateway
            $paymentOrder->order_reference,
            config('app.url') . '/api/webhook/receive-mch',
            $ip,
            $productId
        );

        if ($res['status'] !== 'success' || $res['data']['code'] != 0 || $res['data']['data']['orderState'] == 7) {
            $paymentOrder->order_notify_response = $res;
            $paymentOrder->save();

            return response()->json([
                'success' => false,
                'message' => '支付请求失败，请联系管理员。'
            ], 400);
        }

        $paymentOrder->order_success_response = $res;
        $paymentOrder->remote_order_status = $res['data']['data']['orderState'];
        $paymentOrder->remote_order_id = $res['data']['data']['payOrderId'];
        $paymentOrder->save();

        return response()->json([
            'success' => true,
            'message' => '正在转向支付链接...',
            'order_id' => $paymentOrder->order_reference,
            'payment_url' => $res['data']['data']['payData']
        ]);
    }
}
