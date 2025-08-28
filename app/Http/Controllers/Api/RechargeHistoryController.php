<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;

class RechargeHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->get('per_page', 10);
        
        $orders = PaymentOrder::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        // Format the response
        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_reference' => $order->order_reference,
                'product_name' => $order->product_name,
                'product_price' => $order->product_price,
                'payment_method' => $this->getPaymentMethodDisplay($order->payment_method),
                'status' => $this->getStatusText($order),
                'status_code' => $order->is_success ? 'success' : ($order->remote_order_status > 0 ? 'pending' : 'failed'),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'completed_at' => $order->is_success && $order->updated_at ? $order->updated_at->format('Y-m-d H:i:s') : null,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem()
            ]
        ]);
    }
    
    private function getStatusText($order)
    {
        if ($order->is_success) {
            return '支付成功';
        } elseif ($order->remote_order_status === -1) {
            return '待支付';
        } elseif ($order->remote_order_status > 0 && $order->remote_order_status < 6) {
            return '处理中';
        } else {
            return '支付失败';
        }
    }
    
    private function getPaymentMethodDisplay($method)
    {
        switch ($method) {
            case 'alipay':
                return '支付宝';
            case 'wechat':
                return '微信';
            default:
                return $method ?: '未知';
        }
    }
}