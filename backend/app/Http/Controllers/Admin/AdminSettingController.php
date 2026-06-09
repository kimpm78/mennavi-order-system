<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends AdminBaseController
{
    public function show(Request $request): JsonResponse
    {
        $admin = $this->authenticatedAdmin($request);
        if (! $admin) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        return response()->json([
            'settings' => [
                ['label' => '管理者名', 'value' => $admin->name],
                ['label' => 'メールアドレス', 'value' => $admin->email],
                ['label' => '通知', 'value' => '注文・遅延アラートを受信'],
            ],
        ]);
    }
}
