<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\ForumMember;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class MchPaymentWebhookWebController extends Controller
{

    public function receivePayment(Request $request)
    {
        $log = Log::channel('mch-payment');

        $data = $request->all();

        $log->info($data);

        $orderId = $data['payOrderId'];//平台交易订单号
        $status = $data['state'];
        $actualReceive = $data['amount'];
        $clientId = $data['mchOrderNo'];
        $order = PaymentOrder::query()
            ->where('order_reference', $clientId)
            ->first();

        if (!$order) {
            $log->error('local order ' . $clientId . ' 不存在');
            return response('success');
        }

        if ($order->is_success) {
            return response('success');
        }

        if (empty($order->created_at)) {
            $order->created_at = Carbon::now()->format('Y-m-d H:i:s');
        }

        $order->order_notify_response = $data;
        $order->remote_order_status = ($status == 2 || $status == 5) ? 6 : 3;
        $order->remote_order_id = $orderId;
        $order->receive_amount = $actualReceive;
        $order->save();

        if ($order->remote_order_status >= 6) {
            $order->is_success = 1;
            $order->save();

            $config = config('products.products');
            $productKey = $order->product_key;

            if (!isset($config[$productKey])) {
                $log->error($order->id . ' | ' . $productKey . ' 不存在');
                return response('success');
            }

            $productDetails = $config[$productKey];
            $user = User::find($order->user_id);

            if ($user) {
                $additionalMessage = '';
                try {
                    DB::beginTransaction();

                    if ($productDetails['type'] === 'vip') {
                        // Calculate VIP expiration date
                        $days = $productDetails['days'] ?? 30;
                        $currentVipExpired = $user->vip_expired_at ? Carbon::parse($user->vip_expired_at) : null;

                        // If user already has VIP and it hasn't expired, extend from current expiration
                        // Otherwise, start from now
                        if ($currentVipExpired && $currentVipExpired->isFuture()) {
                            $newExpiration = $currentVipExpired->addDays($days);
                        } else {
                            $newExpiration = Carbon::now()->addDays($days);
                        }

                        // Update user VIP status
                        $user->vip_expired_at = $newExpiration;
                        $user->save();

                        $order->is_finished = 1;
                        $order->save();

                        $additionalMessage = '用户: ' . $user->username . ' 用户ID: ' . $user->id . ' VIP到期: ' . $newExpiration->format('Y-m-d H:i:s');
                        $log->info('VIP activated: ' . $additionalMessage);
                    }

                    DB::commit();
                } catch (\Exception $exception) {
                    DB::rollBack();
                    $error = $order->id . ' | ' . $order->user_id . ' 无法充值';
                    $log->error($exception);
                    $log->error($error);
                    return response('success');
                }
                return response('success');
            } else {
                $log->error($order->id . ' | ' . $order->user_id . ' 不存在');
                return response('success');
            }
        }
        return response('success');
    }
}
