<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Общие помощники функциональных тестов аутентификации.
 *
 * - Клиент ходит по HTTPS: cookie refresh-токена `Secure`, по HTTP браузер (и BrowserKit) её не отправит.
 * - У каждого теста свой случайный IP: состояние login_throttling хранится в кеше и переживает
 *   прогоны, а глобальный лимит считается по IP.
 * - Email уникальные, БД откатывается DAMA после каждого теста.
 */
abstract class AuthApiTestCase extends WebTestCase
{
    protected const string COOKIE_NAME = 'refresh_token';
    protected const string COOKIE_PATH = '/api/auth';
    protected const string PASSWORD = 'correct-horse-battery';

    protected KernelBrowser $client;

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
     * @param array<string, mixed>|null $body
     */
    protected function jsonRequest(string $method, string $uri, ?array $body = null, ?string $accessToken = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: match (true) {
                null === $body => null,
                // Пустой массив PHP — это `[]`, а API ждёт объект
                [] === $body => '{}',
                default => json_encode($body, \JSON_THROW_ON_ERROR),
            },
        );
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
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    protected function assertProblem(int $status, ?string $detail = null): array
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = $this->json();
        self::assertSame($status, $problem['status']);
        if (null !== $detail) {
            self::assertSame($detail, $problem['detail']);
        }

        return $problem;
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

    /**
     * Cookie refresh-токена, выставленная последним ответом (Set-Cookie).
     */
    protected function responseRefreshCookie(): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (self::COOKIE_NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * Проверяет, что ответ выставил новый refresh-токен с нужными атрибутами, и возвращает его.
     */
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

    /**
     * Refresh-токен, который клиент отправит следующим запросом (из cookie jar).
     */
    protected function jarRefreshToken(): ?string
    {
        return $this->client->getCookieJar()->get(self::COOKIE_NAME, self::COOKIE_PATH)?->getValue();
    }

    /**
     * Подкладывает в cookie jar произвольный refresh-токен (например, уже использованный).
     */
    protected function setJarRefreshToken(string $token): void
    {
        $this->client->getCookieJar()->set(
            new BrowserCookie(self::COOKIE_NAME, $token, null, self::COOKIE_PATH, 'localhost', true, true, false, 'strict'),
        );
    }

    /**
     * Проверяет форму `user`.
     *
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
