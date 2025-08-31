<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RedemptionCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RedemptionCodeController extends Controller
{
    /**
     * Third-party API to create redemption codes
     * Requires API key authentication
     */
    public function create(Request $request)
    {

        // Simple API key authentication
        $apiKey = $request->header('X-API-Key');
        if ($apiKey !== config('redemption.api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'type' => 'required|string|in:vip',
            'value' => 'required|integer|min:1|max:365', // Days for VIP
            'reference' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $code = RedemptionCode::create([
                'code' => RedemptionCode::generateCode(strtoupper($request->type)),
                'type' => $request->type,
                'value' => $request->value,
                'reference' => $request->reference,
                'is_active' => true,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'code' => $code->code,
                'type' => $code->type,
                'value' => $code->value,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create redemption codes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * User API to redeem a code
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => '请先登录'], 401);
        }

        $code = RedemptionCode::where('code', $request->code)->first();

        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => '兑换码不存在'
            ], 404);
        }

        if (!$code->isRedeemable()) {
            if ($code->redeemed_by) {
                return response()->json([
                    'success' => false,
                    'message' => '该兑换码已被使用'
                ], 400);
            }
            return response()->json([
                'success' => false,
                'message' => '该兑换码已失效'
            ], 400);
        }

        DB::beginTransaction();
        try {
            if ($code->redeem($user)) {
                DB::commit();

                // Get updated user info
                $user->refresh();

                return response()->json([
                    'success' => true,
                    'message' => "成功兑换 {$code->value} 天 VIP",
                    'vip_expire' => $user->vip_expired_at,
                    'type' => $code->type,
                    'value' => $code->value,
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => '兑换失败，请稍后再试'
                ], 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '兑换失败：' . $e->getMessage()
            ], 500);
        }
    }
}
