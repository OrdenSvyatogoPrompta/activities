<?php

declare(strict_types=1);

namespace App\Tests\Functional\Activity;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Запросы к API от имени фикстурных пользователей (AppFixtures) и разбор ответов.
 *
 * Access-токен выпускается напрямую через JWTTokenManagerInterface — без логина, чтобы
 * тесты не зависели от группы C и не упирались в login_throttling.
 *
 * Требует свойство `$client` (KernelBrowser), созданное в setUp().
 */
trait ApiTestTrait
{
    private KernelBrowser $client;

    private function user(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user, \sprintf('Фикстурный пользователь %s не найден — выполните make test-db', $email));

        return $user;
    }

    private function tokenFor(string $email): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($this->user($email));
    }

    /**
     * @param string|null               $email от чьего имени (null — без токена)
     * @param array<string, mixed>|null $body  JSON-тело
     */
    private function api(?string $email, string $method, string $uri, ?array $body = null): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $email) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($email);
        }
        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function assertProblem(int $status): array
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = $this->json();
        self::assertSame($status, $problem['status']);
        self::assertIsString($problem['detail']);

        return $problem;
    }

    /**
     * Пути полей с ошибками валидации из ответа 422.
     *
     * @return list<string>
     */
    private function violationPaths(): array
    {
        $problem = $this->assertProblem(422);
        self::assertIsArray($problem['violations'] ?? null);

        return array_map(static fn (array $violation): string => $violation['propertyPath'], $problem['violations']);
    }
}
