<?php

namespace App\Http\Controllers\Admin;

use App\Models\MainVisualSetting;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSettingController extends AdminBaseController
{
    private const DEFAULT_MAIN_VISUAL_TITLE = '今日の一杯を見つけよう';
    private const DEFAULT_MAIN_VISUAL_DESCRIPTION = '厳選された究極のラーメン店ガイド。あなたの気分に合わせた最高の一杯をご提案します。';

    public function show(Request $request): JsonResponse
    {
        $admin = $this->authenticatedAdmin($request);
        if (! $admin) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        return response()->json([
            'settings' => $this->basicSettingsResponse($admin),
            'main_visual_setting' => $this->mainVisualSettingResponse($this->activeMainVisualSetting()),
        ]);
    }

    public function updateBasicSettings(Request $request): JsonResponse
    {
        $admin = $this->authenticatedAdmin($request);
        if (! $admin) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'admin_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $admin->update($validated);
        $freshAdmin = $admin->fresh();

        return response()->json([
            'settings' => $this->basicSettingsResponse($freshAdmin),
            'admin' => [
                'name' => $freshAdmin->name,
                'email' => $freshAdmin->email,
            ],
        ]);
    }

    public function publicMainVisualSetting(): JsonResponse
    {
        return response()->json([
            'main_visual_setting' => $this->mainVisualSettingResponse($this->activeMainVisualSetting()),
        ]);
    }

    public function updateMainVisualSetting(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $setting = MainVisualSetting::query()->firstOrNew(['id' => 1]);
        $setting->fill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ])->save();

        return response()->json([
            'main_visual_setting' => $this->mainVisualSettingResponse($setting->fresh()),
        ]);
    }

    public function uploadMainVisualImage(Request $request, ImageStorageService $imageStorage): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $setting = MainVisualSetting::query()->firstOrNew(['id' => 1]);
        if (! $setting->exists) {
            $setting->fill([
                'title' => self::DEFAULT_MAIN_VISUAL_TITLE,
                'description' => self::DEFAULT_MAIN_VISUAL_DESCRIPTION,
                'is_active' => true,
            ])->save();
        }

        $file = $validated['image'];
        $setting->update(['image_path' => $imageStorage->upload($file, 'settings/main-visual')]);

        return response()->json([
            'main_visual_setting' => $this->mainVisualSettingResponse($setting->fresh()),
        ]);
    }

    private function activeMainVisualSetting(): ?MainVisualSetting
    {
        return MainVisualSetting::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<int, array{key: string, label: string, value: string|bool}>
     */
    private function basicSettingsResponse($admin): array
    {
        return [
            ['key' => 'admin_name', 'label' => '管理者名', 'value' => $admin->name],
            ['key' => 'admin_email', 'label' => 'メールアドレス', 'value' => $admin->email],
            ['key' => 'admin_notifications_enabled', 'label' => '通知', 'value' => $admin->admin_notifications_enabled],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mainVisualSettingResponse(?MainVisualSetting $setting): array
    {
        return [
            'id' => $setting?->id,
            'title' => $setting?->title ?: self::DEFAULT_MAIN_VISUAL_TITLE,
            'description' => $setting?->description ?: self::DEFAULT_MAIN_VISUAL_DESCRIPTION,
            'image_path' => $setting?->image_path,
        ];
    }
}
