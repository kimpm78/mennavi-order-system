<?php

namespace App\Http\Controllers\Admin;

use App\Models\MainVisualSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'settings' => [
                ['label' => '管理者名', 'value' => $admin->name],
                ['label' => 'メールアドレス', 'value' => $admin->email],
                ['label' => '通知', 'value' => '注文・遅延アラートを受信'],
            ],
            'main_visual_setting' => $this->mainVisualSettingResponse($this->activeMainVisualSetting()),
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

    public function uploadMainVisualImage(Request $request): JsonResponse
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
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileName = now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension;
        $path = $file->storeAs('settings/main-visual', $fileName, 'public');

        $setting->update(['image_path' => Storage::url($path)]);

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
