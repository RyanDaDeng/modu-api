<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PaymentOrder;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AffiliateController extends Controller
{
    /**
     * Get affiliate dashboard data
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        
        // Check if user is an affiliate
        $affiliate = Affiliate::where('user_id', $user->id)->first();
        
        // Get invited users
        $invitedUsers = User::where('inviter_id', $user->id)
            ->select('id', 'name', 'email', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get payment orders from invited users
        $paymentOrders = PaymentOrder::where('inviter_id', $user->id)
            ->where('is_success', 1)
            ->where('is_finished', 1)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->select('id', 'user_id', 'product_name', 'receive_amount', 'payment_method', 'created_at')
            ->paginate(20);
        
        // Calculate statistics
        $stats = [
            'total_invited' => $invitedUsers->count(),
            'total_orders' => PaymentOrder::where('inviter_id', $user->id)
                ->where('is_success', 1)
                ->where('is_finished', 1)
                ->count(),
            'total_revenue' => PaymentOrder::where('inviter_id', $user->id)
                ->where('is_success', 1)
                ->where('is_finished', 1)
                ->sum('receive_amount'),
            'commission_rate' => $affiliate ? $affiliate->rate : 0,
            'estimated_commission' => 0,
        ];
        
        // Calculate estimated commission if user is an affiliate
        if ($affiliate) {
            $stats['estimated_commission'] = ($stats['total_revenue'] * $affiliate->rate) / 100;
        }
        
        // Get referral query parameter
        $referralLink = '?fromid=' . $user->id;
        
        return response()->json([
            'success' => true,
            'data' => [
                'is_affiliate' => $affiliate ? true : false,
                'affiliate' => $affiliate,
                'stats' => $stats,
                'invited_users' => $invitedUsers,
                'payment_orders' => $paymentOrders,
                'referral_link' => $referralLink,
            ]
        ]);
    }
}