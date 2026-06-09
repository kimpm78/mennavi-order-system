<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $todayOrders = Order::whereDate('ordered_at', today())->count();
        $yesterdayOrders = Order::whereDate('ordered_at', today()->subDay())->count();
        $todaySales = Order::whereDate('ordered_at', today())
            ->where('payment_status', 'paid')
            ->sum('total_amount');
        $yesterdaySales = Order::whereDate('ordered_at', today()->subDay())
            ->where('payment_status', 'paid')
            ->sum('total_amount');
        $activeOrders = Order::with('items')
            ->whereIn('order_status', ['received', 'cooking'])
            ->latest('ordered_at')
            ->limit(15)
            ->get();

        return response()->json([
            'summary' => [
                'today_orders' => $todayOrders,
                'today_sales' => (int) $todaySales,
                'today_orders_change_rate' => $this->changeRate($todayOrders, $yesterdayOrders),
                'today_sales_change_rate' => $this->changeRate((int) $todaySales, (int) $yesterdaySales),
                'average_cooking_minutes' => 18,
                'kitchen_load' => 82,
            ],
            'orders' => $activeOrders->map(fn (Order $order) => $this->orderRow($order))->values(),
            'delivery_networks' => [
                ['name' => 'UberEats', 'count' => 8, 'status' => '稼働中'],
                ['name' => 'Wolt', 'count' => 12, 'status' => '稼働中'],
                ['name' => '出前館', 'count' => 0, 'status' => '停止中'],
            ],
            'kitchen_bars' => [26, 36, 30, 46, 60, 55, 40, 33, 26, 18],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function changeRate(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : 100;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => '#' . $order->id,
            'order_number' => $order->order_number,
            'title' => $order->items
                ->map(fn ($item) => "{$item->product_name} ×{$item->quantity}")
                ->join('、'),
            'note' => $order->note ?? '-',
            'type' => $order->receipt_type === 'delivery' ? 'デリバリー' : '店内',
            'elapsed_minutes' => $order->ordered_at ? $order->ordered_at->diffInMinutes(now()) : 0,
            'status' => $order->order_status,
            'total_amount' => $order->total_amount,
        ];
    }
}
