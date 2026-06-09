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

        $orders = Order::with('items')
            ->latest('ordered_at')
            ->limit(50)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'title' => $order->items->map(fn ($item) => "{$item->product_name} ×{$item->quantity}")->join('、'),
                'type' => $order->receipt_type === 'delivery' ? 'デリバリー' : '店内',
                'status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount,
                'ordered_at' => $order->ordered_at?->toISOString(),
            ])
            ->values();

        return response()->json(['orders' => $orders]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'order_status' => ['required', 'string', 'in:received,cooking,completed,canceled'],
        ]);

        $order->forceFill(['order_status' => $validated['order_status']])->save();

        return response()->json(['order' => $order->fresh('items')]);
    }
}
