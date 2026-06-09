<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSalesController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $paidOrders = Order::where('payment_status', 'paid');

        return response()->json([
            'summary' => [
                ['label' => '本日', 'amount' => (int) (clone $paidOrders)->whereDate('ordered_at', today())->sum('total_amount'), 'orders' => (clone $paidOrders)->whereDate('ordered_at', today())->count(), 'rate' => '+0%'],
                ['label' => '今月', 'amount' => (int) (clone $paidOrders)->whereMonth('ordered_at', now()->month)->sum('total_amount'), 'orders' => (clone $paidOrders)->whereMonth('ordered_at', now()->month)->count(), 'rate' => '+0%'],
                ['label' => 'デリバリー', 'amount' => (int) (clone $paidOrders)->where('receipt_type', 'delivery')->sum('total_amount'), 'orders' => (clone $paidOrders)->where('receipt_type', 'delivery')->count(), 'rate' => '+0%'],
            ],
            'bars' => [26, 36, 30, 46, 60, 55, 40, 33, 26, 18],
        ]);
    }
}
