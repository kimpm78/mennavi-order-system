<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    // 利用可能な麺ナビ Plusプラン一覧を取得
    public function plans(): JsonResponse
    {
        $plans = DB::table('subscription_plans')
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    // ログインユーザーの最新契約情報を取得
    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        $subscription = DB::table('user_subscriptions')
            ->join(
                'subscription_plans',
                'user_subscriptions.subscription_plan_id',
                '=',
                'subscription_plans.id',
            )
            ->where('user_subscriptions.user_id', $user->id)
            ->whereNull('user_subscriptions.deleted_at')
            ->orderByDesc('user_subscriptions.id')
            ->select([
                'user_subscriptions.id',
                'user_subscriptions.status',
                'user_subscriptions.started_at',
                'user_subscriptions.current_period_start',
                'user_subscriptions.current_period_end',
                'user_subscriptions.cancel_at_period_end',
                'user_subscriptions.canceled_at',
                'user_subscriptions.ended_at',
                'subscription_plans.code as plan_code',
                'subscription_plans.name as plan_name',
                'subscription_plans.price',
                'subscription_plans.currency',
                'subscription_plans.discount_rate',
                'subscription_plans.free_delivery',
            ])
            ->first();

        return response()->json([
            'subscription' => $subscription,
        ]);
    }

    // 麺ナビ Plusへ申し込む
    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:50'],
            'payment_method_id' => ['nullable', 'integer'],
        ], [
            'plan_code.required' => 'プランを選択してください。',
            'payment_method_id.integer' => '決済方法の形式が正しくありません。',
        ]);

        $plan = DB::table('subscription_plans')
            ->where('code', $validated['plan_code'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan_code' => '選択されたプランは利用できません。',
            ]);
        }

        $activeSubscription = DB::table('user_subscriptions')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('current_period_end', '>', now())
            ->exists();

        if ($activeSubscription) {
            throw ValidationException::withMessages([
                'subscription' => 'すでに麺ナビ Plusを利用中です。',
            ]);
        }

        if (! empty($validated['payment_method_id'])) {
            $ownsPaymentMethod = DB::table('user_payment_methods')
                ->where('id', $validated['payment_method_id'])
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->exists();

            if (! $ownsPaymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => '選択された決済方法が見つかりません。',
                ]);
            }
        }

        $subscription = DB::transaction(function () use ($user, $plan, $validated) {
            $now = now();
            $periodEnd = $now->copy()->addMonth();

            $subscriptionId = DB::table('user_subscriptions')->insertGetId([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => $now,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
                'provider' => 'payjp',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('subscription_payments')->insert([
                'user_subscription_id' => $subscriptionId,
                'user_payment_method_id' => $validated['payment_method_id'] ?? null,
                'provider' => 'payjp',
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'payment_status' => 'paid',
                'period_start' => $now,
                'period_end' => $periodEnd,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('user_subscriptions')
                ->where('id', $subscriptionId)
                ->first();
        });

        return response()->json([
            'message' => '麺ナビ Plusへの申し込みが完了しました。',
            'subscription' => $subscription,
        ], 201);
    }

    // 現在の契約期間終了時に解約する
    public function cancel(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        $subscription = $this->findActiveSubscription($user->id);

        DB::table('user_subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'cancel_at_period_end' => true,
                'canceled_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => '契約期間終了時の解約を受け付けました。',
            'subscription' => DB::table('user_subscriptions')
                ->where('id', $subscription->id)
                ->first(),
        ]);
    }

    // 契約期間終了時の解約予約を取り消す
    public function resume(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        $subscription = $this->findActiveSubscription($user->id);

        DB::table('user_subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => '解約予約を取り消しました。',
            'subscription' => DB::table('user_subscriptions')
                ->where('id', $subscription->id)
                ->first(),
        ]);
    }

    // ログインユーザーのサブスクリプション決済履歴を取得
    public function payments(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user) {
            return response()->json([
                'message' => '認証が必要です。',
            ], 401);
        }

        $payments = DB::table('subscription_payments')
            ->join(
                'user_subscriptions',
                'subscription_payments.user_subscription_id',
                '=',
                'user_subscriptions.id',
            )
            ->where('user_subscriptions.user_id', $user->id)
            ->whereNull('subscription_payments.deleted_at')
            ->orderByDesc('subscription_payments.id')
            ->select('subscription_payments.*')
            ->paginate(10);

        return response()->json([
            'payments' => $payments,
        ]);
    }

    // PAY.JPから送信されたサブスクリプションイベントを受信
    public function webhook(Request $request): JsonResponse
    {
        Log::info('PAY.JP subscription webhook received.', [
            'event_type' => $request->input('type'),
            'event_id' => $request->input('id'),
        ]);

        // TODO: PAY.JPの署名検証とイベント種別ごとの更新処理を追加
        return response()->json([
            'message' => 'Webhookを受信しました。',
        ]);
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

    // ログインユーザーの有効な契約を取得
    private function findActiveSubscription(int $userId): object
    {
        $subscription = DB::table('user_subscriptions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('current_period_end', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => '有効な契約が見つかりません。',
            ]);
        }

        return $subscription;
    }
}
