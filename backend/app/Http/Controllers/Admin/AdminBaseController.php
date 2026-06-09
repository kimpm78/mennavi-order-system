<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\Request;

abstract class AdminBaseController extends Controller
{
    protected function authenticatedAdmin(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $apiToken = UserApiToken::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $apiToken || $apiToken->user->role !== 'admin' || $apiToken->user->status !== 'active') {
            return null;
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        return $apiToken->user;
    }
}
