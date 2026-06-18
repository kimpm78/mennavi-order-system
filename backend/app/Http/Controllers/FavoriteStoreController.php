<?php

namespace App\Http\Controllers;

use App\Models\FavoriteStore;
use App\Models\Store;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        return response()->json([
            'store_ids' => FavoriteStore::where('user_id', $user->id)
                ->latest()
                ->pluck('store_id')
                ->values(),
        ]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        if (! $store->is_active) {
            return response()->json(['message' => '店舗が見つかりません。'], 404);
        }

        FavoriteStore::firstOrCreate([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        return $this->index($request);
    }

    public function destroy(Request $request, Store $store): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        FavoriteStore::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->delete();

        return $this->index($request);
    }

    private function authenticatedUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $apiToken = UserApiToken::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $apiToken || $apiToken->user->status !== 'active') {
            return null;
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        return $apiToken->user;
    }
}
