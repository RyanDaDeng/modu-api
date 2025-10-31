<?php

namespace App\Http\Controllers\Api\Common;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

/**
 * @author Ryan
 * Trait ApiStructureTrait
 * @package App\Modules\Core
 */
trait ApiStructureTrait
{

    /**
     * @param Builder $build
     * @param $data
     * @return Builder
     */
    public function filter(Builder $build, $data)
    {
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $build->where($key, $value);
            }
        }
        return $build;
    }

    /**
     * @param array $data
     * @param string $systemCode
     * @param int $httpCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSuccess($data = [], $systemCode = '', $httpCode = 200)
    {
        return $this->sendResponse('success', $data, $systemCode, $httpCode);
    }

    public function sendSuccessWithNotifyAndAlert($message, $data, $alertMessage)
    {
        return $this->sendResponse('success', $data, '', 200, true, $message, $alertMessage);
    }

    public function sendSuccessWithNotify($message, $data = [], $systemCode = '', $httpCode = 200)
    {
        return $this->sendResponse('success', $data, $systemCode, $httpCode, true, $message);
    }

    /**
     * @param array $data
     * @param string $systemCode
     * @param int $httpCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendError($message, $data = [], $systemCode = '', $httpCode = 400)
    {
        return $this->sendResponse('error', $data, $systemCode, $httpCode, true, $message);
    }

    public function sendErrorWithMessage($message, $data = [], $systemCode = '', $httpCode = 400)
    {
        return $this->sendResponse('error', $data, $systemCode, $httpCode, true, $message);
    }


    /**
     * @param $status
     * @param array $data
     * @param string $systemCode
     * @param int $httpCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResponse($status, $data = [], $systemCode = '', $httpCode = 200, $notify = false, $message = '', $alertMessage = '')
    {
        $response = [
            'status' => $status,
            'success' => $status === 'success',
            'data' => $data,
            'notify' => $notify,
            'message' => $message,
            'alertMessage' => $alertMessage,
        ];

        if (!empty($systemCode)) {
            $response['code'] = $systemCode;
        }
        return Response::json($response, $httpCode);
    }
}
