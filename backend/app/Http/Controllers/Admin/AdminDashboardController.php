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
        $dashboardOrders = Order::with(['items.product.store', 'user'])
            ->whereIn('order_status', ['received', 'cooking', 'delivering', 'completed'])
            ->latest('ordered_at')
            ->limit(30)
            ->get();
        $cookingOrders = Order::where('order_status', 'cooking')
            ->whereNotNull('ordered_at')
            ->get();
        $todayOrdersForTimeRanges = Order::whereDate('ordered_at', today())
            ->where('order_status', '<>', 'canceled')
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
            'orders' => $dashboardOrders->map(fn (Order $order) => $this->orderRow($order))->values(),
            'kitchen_bars' => $this->kitchenBars($todayOrdersForTimeRanges),
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
     * @return array<int, array{label: string, order_count: int, load: int}>
     */
    private function kitchenBars($orders): array
    {
        $buckets = array_fill(0, 12, 0);

        foreach ($orders as $order) {
            if (! $order->ordered_at) {
                continue;
            }

            $hour = (int) $order->ordered_at->format('G');

            if ($hour < 10 || $hour >= 22) {
                continue;
            }

            $buckets[$hour - 10]++;
        }

        $ranges = [
            ['label' => '10:00-12:00', 'start' => 0, 'end' => 2],
            ['label' => '12:00-14:00', 'start' => 2, 'end' => 4],
            ['label' => '14:00-16:00', 'start' => 4, 'end' => 6],
            ['label' => '16:00-19:00', 'start' => 6, 'end' => 9],
            ['label' => '19:00-22:00', 'start' => 9, 'end' => 12],
        ];

        $rangeCounts = array_map(
            fn (array $range): int => array_sum(array_slice($buckets, $range['start'], $range['end'] - $range['start'])),
            $ranges,
        );
        $max = max($rangeCounts);

        if ($max === 0) {
            return array_map(fn (array $range): array => [
                'label' => $range['label'],
                'order_count' => 0,
                'load' => 0,
            ], $ranges);
        }

        return array_map(fn (array $range, int $count): array => [
            'label' => $range['label'],
            'order_count' => $count,
            'load' => $count === 0 ? 0 : max(12, (int) round($count / $max * 100)),
        ], $ranges, $rangeCounts);
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
            'store_name' => $order->items->first()?->product?->store?->name,
            'title' => $order->items
                ->map(fn ($item) => "{$item->product_name} ×{$item->quantity}")
                ->join('、'),
            'note' => $order->note ?? '-',
            'type' => $order->receipt_type === 'delivery' ? 'デリバリー' : '店内',
            'elapsed_minutes' => $order->ordered_at ? $order->ordered_at->diffInMinutes(now()) : 0,
            'status' => $order->order_status,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'receipt_type' => $order->receipt_type,
            'delivery_staff_name' => $order->delivery_staff_name,
            'total_amount' => $order->total_amount,
            'created_at' => $order->ordered_at?->toISOString(),
            'ordered_at' => $order->ordered_at?->toISOString(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'name' => $item->product_name,
                'unit_price' => $item->unit_price,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
                'store_name' => $item->product?->store?->name,
            ])->values(),
        ];
    }
}
