<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostalCodeApiTest extends TestCase
{
    public function test_postal_code_returns_formatted_address(): void
    {
        Http::fake([
            'zipcloud.ibsnet.co.jp/*' => Http::response([
                'results' => [
                    [
                        'address1' => '東京都',
                        'address2' => '渋谷区',
                        'address3' => '神南',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/postal-code/150-0041')
            ->assertOk()
            ->assertJsonPath('postal_code', '150-0041')
            ->assertJsonPath('address', '東京都渋谷区神南');
    }

    public function test_postal_code_requires_seven_digits(): void
    {
        $this->getJson('/api/postal-code/123')
            ->assertUnprocessable()
            ->assertJsonPath('message', '郵便番号は7桁で入力してください。');
    }
}
