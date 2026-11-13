<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use App\Tests\Functional\ApiTestCase;

final class PlatformSmokeTest extends ApiTestCase
{
    public function testPingIsPublic(): void
    {
        $this->client->request('GET', '/api/ping');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json()['status']);
    }

    public function testProtectedRouteWithoutTokenReturns401Problem(): void
    {
        $this->client->request('GET', '/api/me');

        $problem = $this->assertProblem(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame('Требуется вход: access-токен не передан', $problem['detail']);
    }

    public function testProtectedRouteWithInvalidTokenReturns401Problem(): void
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        $problem = $this->assertProblem(401);
        self::assertSame('Access-токен недействителен', $problem['detail']);
    }

    public function testAdminRouteWithoutTokenReturns401Problem(): void
    {
        $this->client->request('GET', '/api/admin/enrollments');

        $this->assertProblem(401);
    }

    public function testUnknownRouteReturns404Problem(): void
    {
        $this->client->request('GET', '/api/no-such-route');

        $problem = $this->assertProblem(404);
        self::assertSame('Маршрут не найден', $problem['detail']);
    }

    public function testWrongMethodReturns405Problem(): void
    {
        $this->client->request('POST', '/api/ping');

        $this->assertProblem(405);
        self::assertResponseHasHeader('Allow');
    }

    public function testLoginWithMalformedJsonReturns400Problem(): void
    {
        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        $this->assertProblem(400);
    }

    public function testLoginWithoutJsonReturns415Problem(): void
    {
        $this->client->request('POST', '/api/auth/login', ['email' => 'a@example.com', 'password' => 'secret']);

        $this->assertProblem(415);
    }

    public function testLoginWithUnknownUserReturns401Problem(): void
    {
        // Уникальный email, чтобы повторные прогоны не упирались в login_throttling
        $email = 'nobody-'.bin2hex(random_bytes(6)).'@example.com';

        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => 'wrong-password'], \JSON_THROW_ON_ERROR),
        );

        $problem = $this->assertProblem(401);
        self::assertSame('Неверный email или пароль', $problem['detail']);
    }

    public function testRefreshWithoutCookieReturns401Problem(): void
    {
        $this->client->request('POST', '/api/auth/refresh');

        $problem = $this->assertProblem(401);
        self::assertSame('Refresh-токен не передан', $problem['detail']);
    }
}
