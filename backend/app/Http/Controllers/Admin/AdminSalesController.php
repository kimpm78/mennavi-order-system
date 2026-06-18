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
        $todayPaidOrders = Order::where('payment_status', 'paid')
            ->whereDate('ordered_at', today())
            ->whereNotNull('ordered_at')
            ->get();

        return response()->json([
            'summary' => [
                ['label' => '本日', 'amount' => (int) (clone $paidOrders)->whereDate('ordered_at', today())->sum('total_amount'), 'orders' => (clone $paidOrders)->whereDate('ordered_at', today())->count(), 'rate' => '+0%'],
                ['label' => '今月', 'amount' => (int) (clone $paidOrders)->whereMonth('ordered_at', now()->month)->sum('total_amount'), 'orders' => (clone $paidOrders)->whereMonth('ordered_at', now()->month)->count(), 'rate' => '+0%'],
                ['label' => 'デリバリー', 'amount' => (int) (clone $paidOrders)->where('receipt_type', 'delivery')->sum('total_amount'), 'orders' => (clone $paidOrders)->where('receipt_type', 'delivery')->count(), 'rate' => '+0%'],
            ],
            'bars' => $this->salesBars($todayPaidOrders),
        ]);
    }

    /**
     * @return array<int, array{label: string, order_count: int, load: int}>
     */
    private function salesBars($orders): array
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
}
