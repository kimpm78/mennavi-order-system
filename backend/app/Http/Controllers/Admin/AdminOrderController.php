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

        $orders = Order::with(['items', 'user'])
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
            'order_status' => ['required', 'string', 'in:received,cooking,completed,canceled'],
        ]);

        $order->forceFill(['order_status' => $validated['order_status']])->save();

        $freshOrder = $order->fresh(['items', 'user']) ?? $order->load(['items', 'user']);

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
            'total_amount' => $order->total_amount,
            'created_at' => $order->ordered_at?->toISOString(),
            'ordered_at' => $order->ordered_at?->toISOString(),
        ];
    }
}
