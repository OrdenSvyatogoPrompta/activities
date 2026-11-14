<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

/**
 * Полный цикл сессии: register → me → refresh → logout → refresh(401).
 */
final class AuthFlowTest extends AuthApiTestCase
{
    public function testFullSessionCycle(): void
    {
        $email = self::uniqueEmail('cycle');

        // register
        $registered = $this->register($email, 'Цикл Проверка');
        $firstRefresh = $this->assertRefreshCookieIssued();
        self::assertIsString($registered['accessToken']);

        // me
        $this->jsonRequest('GET', '/api/me', accessToken: $registered['accessToken']);
        self::assertResponseIsSuccessful();
        self::assertSame($registered['user'], $this->json());

        // refresh: новый access-токен, пользователь и новый refresh-токен (ротация)
        $this->jsonRequest('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();
        $refreshed = $this->json();
        self::assertSame(['accessToken', 'user'], array_keys($refreshed));
        self::assertIsString($refreshed['accessToken']);
        self::assertSame($registered['user'], $refreshed['user']);
        $secondRefresh = $this->assertRefreshCookieIssued();
        self::assertNotSame($firstRefresh, $secondRefresh);
        self::assertSame($secondRefresh, $this->jarRefreshToken());

        // новый access-токен рабочий
        $this->jsonRequest('GET', '/api/me', accessToken: $refreshed['accessToken']);
        self::assertResponseIsSuccessful();
        self::assertSame($email, $this->json()['email']);

        // logout: 204, cookie удалена
        $this->jsonRequest('POST', '/api/auth/logout', accessToken: $refreshed['accessToken']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $cleared = $this->responseRefreshCookie();
        self::assertNotNull($cleared);
        self::assertTrue($cleared->isCleared());
        self::assertSame(self::COOKIE_PATH, $cleared->getPath());
        self::assertNull($this->jarRefreshToken());

        // refresh после logout: cookie больше нет
        $this->jsonRequest('POST', '/api/auth/refresh');
        $this->assertProblem(401, 'Refresh-токен не передан');

        // и даже сохранённый на стороне клиента токен уже инвалидирован на сервере
        $this->setJarRefreshToken($secondRefresh);
        $this->jsonRequest('POST', '/api/auth/refresh');
        $this->assertProblem(401, 'Refresh-токен недействителен или истёк');
    }

    public function testUsedRefreshTokenCannotBeReused(): void
    {
        $this->register();
        $original = $this->assertRefreshCookieIssued();

        $this->jsonRequest('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();

        // single_use: использованный токен удалён
        $this->setJarRefreshToken($original);
        $this->jsonRequest('POST', '/api/auth/refresh');
        $this->assertProblem(401, 'Refresh-токен недействителен или истёк');
    }

    public function testRefreshWithUnknownTokenReturns401(): void
    {
        $this->setJarRefreshToken(bin2hex(random_bytes(32)));
        $this->jsonRequest('POST', '/api/auth/refresh');

        $this->assertProblem(401, 'Refresh-токен недействителен или истёк');
    }

    public function testRefreshCookieIsNotSentOverPlainHttp(): void
    {
        $this->register();
        $this->assertRefreshCookieIssued();

        // Cookie Secure: по HTTP клиент её не отправит
        $this->client->setServerParameter('HTTPS', 'off');
        $this->client->request('POST', 'http://localhost/api/auth/refresh');

        $this->assertProblem(401, 'Refresh-токен не передан');
    }

    public function testLogoutWithoutCookieIsIdempotent(): void
    {
        $this->jsonRequest('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
        $cleared = $this->responseRefreshCookie();
        self::assertNotNull($cleared);
        self::assertTrue($cleared->isCleared());
    }

    public function testLogoutDoesNotRequireAccessToken(): void
    {
        $this->register();
        $token = $this->assertRefreshCookieIssued();

        $this->jsonRequest('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->setJarRefreshToken($token);
        $this->jsonRequest('POST', '/api/auth/refresh');
        $this->assertProblem(401, 'Refresh-токен недействителен или истёк');
    }
}
