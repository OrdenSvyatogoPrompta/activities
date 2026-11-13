<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * HTTPS — потому что cookie refresh-токена `Secure`; случайный IP — потому что состояние
 * login_throttling хранится в кеше и переживает прогоны.
 */
abstract class AuthApiTestCase extends ApiTestCase
{
    protected const string COOKIE_NAME = 'refresh_token';
    protected const string COOKIE_PATH = '/api/auth';
    protected const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        $this->client = self::createClient(server: [
            'HTTPS' => 'on',
            'REMOTE_ADDR' => \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254)),
        ]);
    }

    protected static function uniqueEmail(string $prefix = 'user'): string
    {
        return $prefix.'-'.bin2hex(random_bytes(6)).'@example.com';
    }

    /**
     * @return array<string, mixed> тело ответа `{ accessToken, user }`
     */
    protected function register(?string $email = null, string $name = 'Тестовый Пользователь', string $password = self::PASSWORD): array
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'name' => $name,
            'email' => $email ?? self::uniqueEmail(),
            'password' => $password,
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->json();
    }

    /**
     * @return list<string> propertyPath всех нарушений из ответа 422
     */
    protected function assertViolations(string ...$expectedPaths): array
    {
        $problem = $this->assertProblem(422);
        self::assertIsArray($problem['violations']);

        $paths = array_values(array_unique(array_column($problem['violations'], 'propertyPath')));
        sort($paths);
        $expected = $expectedPaths;
        sort($expected);
        self::assertSame($expected, $paths);

        foreach ($problem['violations'] as $violation) {
            self::assertMatchesRegularExpression('/\p{Cyrillic}/u', $violation['message'], 'Сообщения валидации — на русском');
        }

        return $problem['violations'];
    }

    protected function responseRefreshCookie(): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (self::COOKIE_NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }

    protected function assertRefreshCookieIssued(): string
    {
        $cookie = $this->responseRefreshCookie();
        self::assertNotNull($cookie, 'Ответ должен выставить cookie '.self::COOKIE_NAME);
        self::assertNotEmpty($cookie->getValue());
        self::assertFalse($cookie->isCleared());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertSame(self::COOKIE_PATH, $cookie->getPath());

        return (string) $cookie->getValue();
    }

    protected function jarRefreshToken(): ?string
    {
        return $this->client->getCookieJar()->get(self::COOKIE_NAME, self::COOKIE_PATH)?->getValue();
    }

    protected function setJarRefreshToken(string $token): void
    {
        $this->client->getCookieJar()->set(
            new BrowserCookie(self::COOKIE_NAME, $token, null, self::COOKIE_PATH, 'localhost', true, true, false, 'strict'),
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    protected static function assertUserShape(array $user): void
    {
        self::assertSame(['id', 'name', 'email', 'roles', 'stats'], array_keys($user));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $user['id']);
        self::assertContains('ROLE_USER', $user['roles']);
        self::assertSame(['enrollments', 'activities', 'children'], array_keys($user['stats']));
        foreach ($user['stats'] as $counter) {
            self::assertIsInt($counter);
        }
    }
}
