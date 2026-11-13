<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke-тесты платформы (группа A): маршрутизация, firewall, формат ошибок.
 */
final class PlatformSmokeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testPingIsPublic(): void
    {
        $this->client->request('GET', '/api/ping');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json()['status']);
    }

    public function testProtectedRouteWithoutTokenReturns401Problem(): void
    {
        $this->client->request('GET', '/api/_test/protected');

        $problem = $this->assertProblem(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame('Требуется вход: access-токен не передан', $problem['detail']);
    }

    public function testProtectedRouteWithInvalidTokenReturns401Problem(): void
    {
        $this->client->request('GET', '/api/_test/protected', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        $problem = $this->assertProblem(401);
        self::assertSame('Access-токен недействителен', $problem['detail']);
    }

    public function testAdminRouteWithoutTokenReturns401Problem(): void
    {
        $this->client->request('GET', '/api/admin/_test/protected');

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

    /**
     * @return array<string, mixed>
     */
    private function assertProblem(int $status): array
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = $this->json();
        self::assertSame('about:blank', $problem['type']);
        self::assertSame($status, $problem['status']);
        self::assertIsString($problem['title']);
        self::assertNotSame('', $problem['title']);
        self::assertIsString($problem['detail']);

        return $problem;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
