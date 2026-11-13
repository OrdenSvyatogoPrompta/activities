<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Запросы к API от имени фикстурных пользователей: access-токен выпускается напрямую
 * через JWTTokenManagerInterface, без логина (не упираемся в login_throttling).
 *
 * Тестовая БД — с фикстурами (`make test-db`), каждый тест в своей транзакции
 * (dama/doctrine-test-bundle). Ядро перезагружается между запросами, поэтому сущности
 * после запроса читаются заново через em().
 *
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
 */
trait AdminApiTestTrait
{
    private KernelBrowser $client;

    private function admin(): User
    {
        return $this->user(AppFixtures::ADMIN_EMAIL);
    }

    private function demoUser(): User
    {
        return $this->user(AppFixtures::DEMO_EMAIL);
    }

    private function user(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertNotNull($user, \sprintf('Фикстурный пользователь %s не найден — загрузите фикстуры (make test-db)', $email));

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, mixed>|null $json тело запроса (JSON)
     * @param array<string, mixed>      $query
     */
    private function requestAs(?User $user, string $method, string $uri, ?array $json = null, array $query = []): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $user) {
            $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $content = null;
        if (null !== $json) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($json, \JSON_THROW_ON_ERROR);
        }

        $this->client->request($method, $uri, $query, server: $server, content: $content);
    }

    /**
     * @return array<mixed>
     */
    private function responseJson(): array
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
        $problem = $this->responseJson();
        self::assertSame($status, $problem['status']);

        return $problem;
    }

    /**
     * @param array<string, mixed> $problem
     *
     * @return list<string>
     */
    private static function violationPaths(array $problem): array
    {
        return array_column($problem['violations'] ?? [], 'propertyPath');
    }
}
