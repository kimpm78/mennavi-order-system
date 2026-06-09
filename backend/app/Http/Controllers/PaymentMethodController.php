<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserApiToken;
use App\Models\UserPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $paymentMethods = $user->paymentMethods()
            ->where('provider', 'payjp')
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(fn (UserPaymentMethod $paymentMethod) => $this->paymentMethodResponse($paymentMethod))
            ->values();

        return response()->json([
            'payment_methods' => $paymentMethods,
            'default_payment_method' => $paymentMethods->firstWhere('is_default', true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'payjp_token' => ['required', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $customer = $this->createPayjpCustomer($validated['payjp_token'], $user);
        $card = $this->extractCard($customer);

        $paymentMethod = DB::transaction(function () use ($user, $customer, $card, $validated): UserPaymentMethod {
            if ($validated['is_default'] ?? false) {
                $user->paymentMethods()->update(['is_default' => false]);
            }

            return $user->paymentMethods()->create([
                'provider' => 'payjp',
                'provider_customer_id' => $customer['id'],
                'provider_card_id' => $card['id'] ?? ($customer['default_card'] ?? null),
                'brand' => $card['brand'] ?? null,
                'last4' => $card['last4'] ?? null,
                'exp_month' => $card['exp_month'] ?? null,
                'exp_year' => $card['exp_year'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
            ]);
        });

        return response()->json([
            'payment_method' => $this->paymentMethodResponse($paymentMethod),
        ], 201);
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

    /**
     * @return array<string, mixed>
     */
    private function createPayjpCustomer(string $token, User $user): array
    {
        $secretKey = config('services.payjp.secret_key');

        if (! $secretKey) {
            abort(500, 'PAY.JP secret key is not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.pay.jp/v1/customers', [
                'card' => $token,
                'email' => $user->email,
                'description' => "Mennavi user {$user->id}",
            ]);

        if ($response->failed()) {
            abort(422, $response->json('error.message') ?? 'カード登録に失敗しました。');
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    private function extractCard(array $customer): array
    {
        $cards = $customer['cards']['data'] ?? [];

        if (is_array($cards) && isset($cards[0]) && is_array($cards[0])) {
            return $cards[0];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodResponse(UserPaymentMethod $paymentMethod): array
    {
        return [
            'id' => $paymentMethod->id,
            'provider' => $paymentMethod->provider,
            'brand' => $paymentMethod->brand,
            'last4' => $paymentMethod->last4,
            'exp_month' => $paymentMethod->exp_month,
            'exp_year' => $paymentMethod->exp_year,
            'is_default' => $paymentMethod->is_default,
        ];
    }
}
