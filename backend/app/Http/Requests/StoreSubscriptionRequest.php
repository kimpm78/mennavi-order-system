<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => [
                'required',
                'string',
                'max:50',
                'exists:subscription_plans,code',
            ],
            'payment_method_id' => [
                'required',
                'integer',
                'exists:user_payment_methods,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_code.required' => 'プランを選択してください。',
            'plan_code.exists' => '選択されたプランは利用できません。',
            'payment_method_id.required' => '決済方法を選択してください。',
            'payment_method_id.exists' => '選択された決済方法が見つかりません。',
        ];
    }
}
