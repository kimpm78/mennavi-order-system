<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'address' => $validated['address'] ?? null,
            'role' => 'user',
            'status' => 'active',
        ]);

        return response()->json($this->issueToken($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $user = $this->validateCredentials($request);
        return response()->json($this->issueToken($user));
    }

    public function adminLogin(Request $request): JsonResponse
    {
        $user = $this->validateCredentials($request);

        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'email' => ['管理者アカウントでログインしてください。'],
            ]);
        }

        return response()->json($this->issueToken($user));
    }

    public function me(Request $request): JsonResponse
    {
        $apiToken = $this->apiTokenFromRequest($request);
        if (! $apiToken) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        return response()->json(['user' => $apiToken->user]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $apiToken = $this->apiTokenFromRequest($request);
        if (! $apiToken) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $apiToken->forceFill(['last_used_at' => now()])->save();
        $apiToken->user->forceFill($validated)->save();

        return response()->json(['user' => $apiToken->user->fresh()]);
    }

    public function adminMe(Request $request): JsonResponse
    {
        $apiToken = $this->apiTokenFromRequest($request);

        if (! $apiToken || $apiToken->user->role !== 'admin') {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        return response()->json(['user' => $apiToken->user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->tokenFromRequest($request);

        if ($token) {
            $apiToken = UserApiToken::with('user')
                ->where('token_hash', hash('sha256', $token))
                ->first();

            if ($apiToken) {
                $apiToken->user->carts()->delete();
                $apiToken->delete();
            }
        }

        return response()->json(['message' => 'ログアウトしました。']);
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user): array
    {
        $plainTextToken = Str::random(80);

        $user->forceFill(['last_login_at' => now()])->save();

        UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'web',
            'token_hash' => hash('sha256', $plainTextToken),
        ]);

        return [
            'user' => $user,
            'token' => $plainTextToken,
        ];
    }

    private function tokenFromRequest(Request $request): ?string
    {
        return $request->bearerToken();
    }

    private function validateCredentials(Request $request): User
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['メールアドレスまたはパスワードが正しくありません。'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['このアカウントは現在利用できません。'],
            ]);
        }

        return $user;
    }

    private function apiTokenFromRequest(Request $request): ?UserApiToken
    {
        $token = $this->tokenFromRequest($request);

        if (! $token) {
            return null;
        }

        return UserApiToken::with('user')->where('token_hash', hash('sha256', $token))->first();
    }
}
