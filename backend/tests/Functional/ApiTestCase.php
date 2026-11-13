<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Branch;
use App\Entity\Child;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Repository\ChildRepository;
use App\Repository\ClubRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Нужна тестовая база с фикстурами (`make test-db`); каждый тест откатывает DAMA.
 * Токены выпускаются напрямую, без логина, чтобы не упираться в login_throttling.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function jsonRequest(string $method, string $uri, ?array $body = null, ?string $accessToken = null): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            // Пустой массив PHP кодируется как `[]`, а API ждёт объект
            content: match (true) {
                null === $body => null,
                [] === $body => '{}',
                default => json_encode($body, \JSON_THROW_ON_ERROR),
            },
        );
    }

    /**
     * @return array<mixed>
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
        self::assertSame('about:blank', $problem['type']);
        self::assertSame($status, $problem['status']);
        self::assertNotSame('', $problem['title']);
        self::assertIsString($problem['detail']);
        if (null !== $detail) {
            self::assertSame($detail, $problem['detail']);
        }

        return $problem;
    }

    /**
     * @return list<string>
     */
    protected function violationPaths(): array
    {
        $problem = $this->assertProblem(422);
        self::assertIsArray($problem['violations'] ?? null);

        return array_column($problem['violations'], 'propertyPath');
    }

    protected function tokenFor(User|string $user): string
    {
        return self::getContainer()->get(JWTTokenManagerInterface::class)->create(\is_string($user) ? $this->user($user) : $user);
    }

    /**
     * Токен уходит со всеми следующими запросами клиента.
     */
    protected function loginAs(string $email): User
    {
        $user = $this->user($email);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->tokenFor($user));

        return $user;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function user(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertNotNull($user, \sprintf('Нет пользователя %s — загружены ли фикстуры (make test-db)?', $email));

        return $user;
    }

    protected function club(string $slug): Club
    {
        $club = self::getContainer()->get(ClubRepository::class)->findOneBySlugWithBranches($slug);
        self::assertNotNull($club, \sprintf('Нет кружка %s в фикстурах', $slug));

        return $club;
    }

    protected function branch(string $clubSlug, string $branchName): Branch
    {
        foreach ($this->club($clubSlug)->getBranches() as $branch) {
            if ($branchName === $branch->getName()) {
                return $branch;
            }
        }

        self::fail(\sprintf('Нет филиала «%s» у кружка %s', $branchName, $clubSlug));
    }

    protected function child(string $parentEmail, string $firstName): Child
    {
        foreach (self::getContainer()->get(ChildRepository::class)->findByParent($this->user($parentEmail)) as $child) {
            if ($firstName === $child->getFirstName()) {
                return $child;
            }
        }

        self::fail(\sprintf('Нет ребёнка «%s» у %s', $firstName, $parentEmail));
    }

    /**
     * Первая не отменённая заявка пользователя в кружок.
     */
    protected function enrollmentOf(string $email, string $clubSlug): Enrollment
    {
        foreach (self::getContainer()->get(EnrollmentRepository::class)->findByUser($this->user($email)) as $enrollment) {
            if ($clubSlug === $enrollment->getClub()->getSlug()) {
                return $enrollment;
            }
        }

        self::fail(\sprintf('Нет заявки %s в кружок %s', $email, $clubSlug));
    }
}
