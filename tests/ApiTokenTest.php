<?php

require_once __DIR__ . '/../inc/api_token.php';

use PHPUnit\Framework\TestCase;

class ApiTokenTest extends TestCase
{
    private string|false $previousSecret;

    protected function setUp(): void
    {
        $this->previousSecret = getenv('APP_TOKEN_SECRET');
        putenv('APP_TOKEN_SECRET=test-signing-key-with-at-least-32-characters');
    }

    protected function tearDown(): void
    {
        if ($this->previousSecret === false) {
            putenv('APP_TOKEN_SECRET');
        } else {
            putenv('APP_TOKEN_SECRET=' . $this->previousSecret);
        }
    }

    public function testSignedTokenRoundTripsIdentityClaims(): void
    {
        $token = create_api_token([
            'id' => 42,
            'username' => 'operator',
            'role' => 'operatore',
            'gym_id' => 7,
        ]);

        $claims = decode_api_token($token);

        $this->assertSame(42, $claims['user_id']);
        $this->assertSame('operator', $claims['username']);
        $this->assertSame('OPERATORE', $claims['role']);
        $this->assertSame(7, $claims['gym_id']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $token = create_api_token([
            'id' => 42,
            'username' => 'operator',
            'role' => 'OPERATORE',
            'gym_id' => 7,
        ]);
        [$payload, $signature] = explode('.', $token);
        $payload[0] = $payload[0] === 'A' ? 'B' : 'A';

        $this->assertNull(decode_api_token($payload . '.' . $signature));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $payload = [
            'user_id' => 42,
            'username' => 'operator',
            'role' => 'OPERATORE',
            'gym_id' => 7,
            'iat' => time() - 120,
            'exp' => time() - 60,
        ];
        $encoded = api_token_base64url_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = api_token_base64url_encode(hash_hmac('sha256', $encoded, api_token_secret(), true));

        $this->assertNull(decode_api_token($encoded . '.' . $signature));
    }
}
