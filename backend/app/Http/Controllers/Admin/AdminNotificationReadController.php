<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminNotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationReadController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $admin = $this->authenticatedAdmin($request);
        if (! $admin) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        return response()->json([
            'notification_ids' => AdminNotificationRead::where('user_id', $admin->id)
                ->pluck('notification_id')
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $this->authenticatedAdmin($request);
        if (! $admin) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'notification_id' => ['required', 'string', 'max:120'],
        ]);

        AdminNotificationRead::firstOrCreate(
            [
                'user_id' => $admin->id,
                'notification_id' => $validated['notification_id'],
            ],
            ['read_at' => now()],
        );

        return response()->json(['message' => '通知を既読にしました。'], 201);
    }
}
