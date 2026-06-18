<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'category' => [
                'required',
                'string',
                'max:50',
                Rule::in([
                    '注文について',
                    '配送について',
                    '決済について',
                    '店舗・メニューについて',
                    '会員情報について',
                    'その他',
                ]),
            ],
            'order_number' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $this->authenticatedUser($request);

        $contactMessage = ContactMessage::create([
            ...$validated,
            'user_id' => $user?->id,
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
        ]);

        return response()->json([
            'message' => 'お問い合わせを受け付けました。',
            'contact_message' => [
                'id' => $contactMessage->id,
                'status' => $contactMessage->status,
            ],
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
}
