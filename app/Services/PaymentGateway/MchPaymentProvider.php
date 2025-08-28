<?php

namespace App\Services\PaymentGateway;

use App\Services\AbstractHttpRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MchPaymentProvider extends AbstractHttpRequest
{
    private string $secret;
    private string $mchNo;

    public function __construct()
    {
        $this->mchNo = config('payment.mch.merchant_id');
        $this->secret = config('payment.mch.key');
        $this->client = Http::asJson()->baseUrl(config('payment.mch.api'));
    }

    public function callCreate(
        $amount,
        $clientOrderId,
        $notifyUrl,
        $clientIp4,
        $productId = '1202'
    )
    {
        $payload = [
            'amount' => $amount,
            'clientIp' => $clientIp4,
            'mchNo' => $this->mchNo,
            'mchOrderNo' => $clientOrderId,
            'productId' => $productId,
            'notifyUrl' => $notifyUrl,
            'reqTime' => time(),
        ];
        $sign = $this->getSign($payload);

        $payload['sign'] = $sign;

        return $this->post('/api/pay/unifiedOrder', $payload);
    }


    function getSign($map) {
        $list = array();

        // 遍历map，过滤掉值为空的键值对
        foreach ($map as $entryKey => $entryValue) {
            if ($entryValue !== null && $entryValue !== '') {
                $list[] = $entryKey . '=' . $entryValue . '&';
            }
        }

        // 将list数组进行字典序排序（不区分大小写）
        usort($list, 'strcasecmp');

        // 将排序后的数组拼接成字符串
        $result = implode('', $list);
        $result .= 'key=' . $this->secret;

        // 对结果进行MD5加密并转为大写
        $result = strtoupper(md5($result));

        return $result;
    }
}
