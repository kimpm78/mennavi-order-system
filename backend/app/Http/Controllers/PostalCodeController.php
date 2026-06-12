<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PostalCodeController extends Controller
{
    public function show(Request $request, string $postalCode): JsonResponse
    {
        $normalizedPostalCode = preg_replace('/\D/', '', $postalCode) ?? '';

        if (! preg_match('/^\d{7}$/', $normalizedPostalCode)) {
            return response()->json(['message' => '郵便番号は7桁で入力してください。'], 422);
        }

        $response = Http::timeout(5)->get('https://zipcloud.ibsnet.co.jp/api/search', [
            'zipcode' => $normalizedPostalCode,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => '住所検索に失敗しました。'], 502);
        }

        $results = $response->json('results');
        if (! is_array($results) || ! isset($results[0]) || ! is_array($results[0])) {
            return response()->json(['message' => '該当する住所が見つかりません。'], 404);
        }

        $address = $results[0];

        return response()->json([
            'postal_code' => substr($normalizedPostalCode, 0, 3).'-'.substr($normalizedPostalCode, 3),
            'address' => implode('', array_filter([
                $address['address1'] ?? null,
                $address['address2'] ?? null,
                $address['address3'] ?? null,
            ])),
            'prefecture' => $address['address1'] ?? null,
            'city' => $address['address2'] ?? null,
            'town' => $address['address3'] ?? null,
        ]);
    }
}
