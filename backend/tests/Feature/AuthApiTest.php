<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserApiToken;
use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_password_is_hashed(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Taro Yamada',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '09012345678',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $user = User::where('email', 'taro@example.com')->firstOrFail();

        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertSame('09012345678', $user->phone);
        $this->assertSame('user', $user->role);
        $this->assertDatabaseCount('user_api_tokens', 1);
        $this->assertNotSame($response->json('token'), UserApiToken::firstOrFail()->token_hash);
    }

    public function test_user_can_login_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'customer@example.com',
            'password' => 'password123',
        ]);

        $token = $login->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'customer@example.com');

        $this->assertNotNull(User::where('email', 'customer@example.com')->firstOrFail()->last_login_at);

        Cart::create([
            'user_id' => $user->id,
            'store_name' => '麺処 極 -KIWAMI-',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_user_can_update_delivery_information(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $plainToken = 'delivery-test-token';
        UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'web',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->withToken($plainToken)
            ->patchJson('/api/me', [
                'name' => 'Taro Yamada',
                'phone' => '09012345678',
                'postal_code' => '150-0041',
                'address' => '東京都渋谷区神南1-2-3',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Taro Yamada')
            ->assertJsonPath('user.phone', '09012345678')
            ->assertJsonPath('user.postal_code', '150-0041')
            ->assertJsonPath('user.address', '東京都渋谷区神南1-2-3');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '09012345678',
            'postal_code' => '150-0041',
            'address' => '東京都渋谷区神南1-2-3',
        ]);
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_admin_login_requires_admin_role(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@mennavi.local',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'customer@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@mennavi.local',
            'password' => 'password123',
        ]);

        $token = $login
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('password123'),
            'status' => 'suspended',
        ]);

        $this->postJson('/api/login', [
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();
    }
}
