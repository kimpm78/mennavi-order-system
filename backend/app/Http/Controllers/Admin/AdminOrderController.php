<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $orders = Order::with(['items.product.store', 'user'])
            ->latest('ordered_at')
            ->limit(50)
            ->get()
            ->map(fn (Order $order) => $this->orderRow($order))
            ->values();

        return response()->json(['orders' => $orders]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'order_status' => ['required', 'string', 'in:received,cooking,delivering,completed,canceled'],
            'delivery_staff_name' => ['nullable', 'string', 'in:佐藤A,鈴木B'],
        ]);

        $wasCanceled = $order->order_status === 'canceled';
        $nextValues = ['order_status' => $validated['order_status']];

        if ($validated['order_status'] === 'delivering') {
            if ($order->receipt_type !== 'delivery') {
                return response()->json(['message' => '配達注文のみ配送中に変更できます。'], 422);
            }

            $nextValues['delivery_staff_name'] = $validated['delivery_staff_name'] ?? '佐藤A';
            $nextValues['delivered_at'] = now();
        }

        if ($validated['order_status'] === 'completed') {
            $nextValues['received_at'] = now();
        }

        if ($validated['order_status'] === 'canceled') {
            $nextValues['payment_status'] = $order->payment_status === 'paid' ? 'refunded' : $order->payment_status;
        }

        $order->forceFill($nextValues)->save();

        if ($validated['order_status'] === 'canceled' && ! $wasCanceled && $order->user && $order->earned_points > 0) {
            $order->user->forceFill([
                'point_balance' => max(0, $order->user->point_balance - $order->earned_points),
            ])->save();
        }

        $freshOrder = $order->fresh(['items.product.store', 'user']) ?? $order->load(['items.product.store', 'user']);

        return response()->json(['order' => $this->orderRow($freshOrder)]);
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
            'customer_name' => $order->customer_name,
            'store_name' => $order->items->first()?->product?->store?->name,
            'customer_phone' => $order->customer_phone ?? $order->user?->phone,
            'recipient_phone' => $order->customer_phone ?? $order->user?->phone,
            'shipping_address' => $order->user?->address,
            'title' => $order->items->map(fn ($item) => "{$item->product_name} ×{$item->quantity}")->join('、'),
            'note' => $order->note ?? '-',
            'type' => $order->receipt_type === 'delivery' ? 'デリバリー' : '店内',
            'elapsed_minutes' => $order->ordered_at ? $order->ordered_at->diffInMinutes(now()) : 0,
            'status' => $order->order_status,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'receipt_type' => $order->receipt_type,
            'delivery_staff_name' => $order->delivery_staff_name,
            'delivered_at' => $order->delivered_at?->toISOString(),
            'received_at' => $order->received_at?->toISOString(),
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
