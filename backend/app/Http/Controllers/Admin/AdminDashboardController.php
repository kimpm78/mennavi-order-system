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
        $activeOrders = Order::with(['items', 'user'])
            ->whereIn('order_status', ['received', 'cooking'])
            ->latest('ordered_at')
            ->limit(15)
            ->get();
        $cookingOrders = Order::where('order_status', 'cooking')
            ->whereNotNull('ordered_at')
            ->get();
        $lastUpdatedOrder = Order::latest('updated_at')->first(['updated_at']);
        $averageCookingMinutes = $this->averageElapsedMinutes($cookingOrders);
        $kitchenLoad = min(100, $cookingOrders->count() * 10);

        return response()->json([
            'summary' => [
                'today_orders' => $todayOrders,
                'today_sales' => (int) $todaySales,
                'today_orders_change_rate' => $this->changeRate($todayOrders, $yesterdayOrders),
                'today_sales_change_rate' => $this->changeRate((int) $todaySales, (int) $yesterdaySales),
                'average_cooking_minutes' => $averageCookingMinutes,
                'kitchen_load' => $kitchenLoad,
            ],
            'orders' => $activeOrders->map(fn (Order $order) => $this->orderRow($order))->values(),
            'kitchen_bars' => $this->kitchenBars($cookingOrders),
            'last_updated_at' => $lastUpdatedOrder?->updated_at?->toISOString(),
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

    private function averageElapsedMinutes($orders): int
    {
        if ($orders->isEmpty()) {
            return 0;
        }

        return (int) round($orders->avg(fn (Order $order) => $order->ordered_at->diffInMinutes(now())));
    }

    /**
     * @return array<int>
     */
    private function kitchenBars($orders): array
    {
        if ($orders->isEmpty()) {
            return array_fill(0, 10, 0);
        }

        $buckets = array_fill(0, 10, 0);

        foreach ($orders as $order) {
            $elapsedMinutes = $order->ordered_at->diffInMinutes(now());
            $index = min(9, intdiv($elapsedMinutes, 10));
            $buckets[$index]++;
        }

        $max = max($buckets);

        return array_map(fn (int $count) => $count === 0 ? 8 : max(12, (int) round($count / $max * 100)), $buckets);
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
            'customer_name' => $order->customer_name ?? $order->user?->name,
            'title' => $order->items
                ->map(fn ($item) => "{$item->product_name} ×{$item->quantity}")
                ->join('、'),
            'note' => $order->note ?? '-',
            'type' => $order->receipt_type === 'delivery' ? 'デリバリー' : '店内',
            'elapsed_minutes' => $order->ordered_at ? $order->ordered_at->diffInMinutes(now()) : 0,
            'status' => $order->order_status,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'total_amount' => $order->total_amount,
            'created_at' => $order->ordered_at?->toISOString(),
            'ordered_at' => $order->ordered_at?->toISOString(),
        ];
    }
}
