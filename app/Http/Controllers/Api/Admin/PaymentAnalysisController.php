<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentAnalysisController extends Controller
{
    /**
     * Get payment orders analysis
     */
    public function index(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'product_key' => 'nullable|string',
            'payment_method' => 'nullable|string|in:wechat,alipay',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $perPage = $request->input('per_page', 20);

        // Build query for successful orders
        $query = PaymentOrder::where('is_success', 1)
            ->where('is_finished', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Apply filters
        if ($request->has('product_key')) {
            $query->where('product_key', $request->input('product_key'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        // Get paginated orders
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get summary statistics
        $summary = $this->getSummaryStatistics($startDate, $endDate, $request);

        // Get daily revenue data for chart
        $dailyRevenue = $this->getDailyRevenue($startDate, $endDate, $request);

        // Get product breakdown
        $productBreakdown = $this->getProductBreakdown($startDate, $endDate, $request);

        // Get payment method breakdown
        $paymentMethodBreakdown = $this->getPaymentMethodBreakdown($startDate, $endDate, $request);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'summary' => $summary,
                'daily_revenue' => $dailyRevenue,
                'product_breakdown' => $productBreakdown,
                'payment_method_breakdown' => $paymentMethodBreakdown,
            ]
        ]);
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStatistics($startDate, $endDate, Request $request)
    {
        $query = PaymentOrder::where('is_success', 1)
            ->where('is_finished', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($request->has('product_key')) {
            $query->where('product_key', $request->input('product_key'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        return [
            'total_revenue' => $query->sum('receive_amount'),
            'total_orders' => $query->count(),
            'average_order_value' => $query->avg('receive_amount') ?? 0,
            'unique_users' => $query->distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * Get daily revenue data
     */
    private function getDailyRevenue($startDate, $endDate, Request $request)
    {
        $query = PaymentOrder::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(receive_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('is_success', 1)
            ->where('is_finished', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($request->has('product_key')) {
            $query->where('product_key', $request->input('product_key'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        return $query->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get product breakdown
     */
    private function getProductBreakdown($startDate, $endDate, Request $request)
    {
        $query = PaymentOrder::select(
                'product_key',
                'product_name',
                DB::raw('SUM(receive_amount) as revenue'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('AVG(receive_amount) as avg_price')
            )
            ->where('is_success', 1)
            ->where('is_finished', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        return $query->groupBy('product_key', 'product_name')
            ->orderBy('revenue', 'desc')
            ->get();
    }

    /**
     * Get payment method breakdown
     */
    private function getPaymentMethodBreakdown($startDate, $endDate, Request $request)
    {
        $query = PaymentOrder::select(
                'payment_method',
                DB::raw('SUM(receive_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('is_success', 1)
            ->where('is_finished', 1)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($request->has('product_key')) {
            $query->where('product_key', $request->input('product_key'));
        }

        return $query->groupBy('payment_method')
            ->orderBy('revenue', 'desc')
            ->get();
    }

    /**
     * Get single order details
     */
    public function show($id)
    {
        $order = PaymentOrder::with('user')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
}