<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    // 現在有効な契約を取得
    public function getActiveSubscription(
        int $userId,
    ): ?UserSubscription {
        return UserSubscription::query()
            ->with('plan')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('current_period_start', '<=', now())
            ->where('current_period_end', '>', now())
            ->latest('current_period_end')
            ->first();
    }

    // 麺ナビ Plusへ申し込む
    public function subscribe(
        User $user,
        SubscriptionPlan $plan,
    ): UserSubscription {
        $activeSubscription = $this->getActiveSubscription($user->id);

        if ($activeSubscription) {
            throw ValidationException::withMessages([
                'subscription' => 'すでに麺ナビ Plusを利用中です。',
            ]);
        }

        return DB::transaction(function () use ($user, $plan) {
            $startedAt = now();

            return UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => $startedAt,
                'current_period_start' => $startedAt,
                'current_period_end' => $startedAt->copy()->addMonth(),
                'cancel_at_period_end' => false,
                'provider' => 'payjp',
            ]);
        });
    }

    // 契約期間終了時の解約を予約
    public function cancel(
        UserSubscription $subscription,
    ): UserSubscription {
        $subscription->update([
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
        ]);

        return $subscription->fresh('plan');
    }

    // 解約予約を取り消す
    public function resume(
        UserSubscription $subscription,
    ): UserSubscription {
        $subscription->update([
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        return $subscription->fresh('plan');
    }
}