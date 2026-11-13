<?php

declare(strict_types=1);

namespace App\Tests\Functional\Enrollment;

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

/**
 * Помощники функциональных тестов заявок: JWT для фикстурных пользователей,
 * поиск фикстурных сущностей, JSON-запросы и проверка problem+json.
 *
 * Тесты ожидают базу activities_test с фикстурами (`make test-db`); каждый тест
 * откатывается DAMA DoctrineTestBundle.
 *
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
 */
trait EnrollmentApiTestTrait
{
    private KernelBrowser $client;

    /**
     * Ставит клиенту заголовок `Authorization: Bearer <access>` пользователя фикстур.
     */
    private function loginAs(string $email): User
    {
        $user = $this->user($email);
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        return $user;
    }

    private function user(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertNotNull($user, \sprintf('Нет пользователя %s — загружены ли фикстуры (make test-db)?', $email));

        return $user;
    }

    private function club(string $slug): Club
    {
        $club = self::getContainer()->get(ClubRepository::class)->findOneBySlugWithBranches($slug);
        self::assertNotNull($club, \sprintf('Нет кружка %s в фикстурах', $slug));

        return $club;
    }

    private function branch(string $clubSlug, string $branchName): Branch
    {
        foreach ($this->club($clubSlug)->getBranches() as $branch) {
            if ($branchName === $branch->getName()) {
                return $branch;
            }
        }

        self::fail(\sprintf('Нет филиала «%s» у кружка %s', $branchName, $clubSlug));
    }

    private function child(string $parentEmail, string $firstName): Child
    {
        foreach (self::getContainer()->get(ChildRepository::class)->findByParent($this->user($parentEmail)) as $child) {
            if ($firstName === $child->getFirstName()) {
                return $child;
            }
        }

        self::fail(\sprintf('Нет ребёнка «%s» у %s', $firstName, $parentEmail));
    }

    /**
     * Первая заявка пользователя в кружок (любой статус, кроме cancelled).
     */
    private function enrollmentOf(string $email, string $clubSlug): Enrollment
    {
        foreach (self::getContainer()->get(EnrollmentRepository::class)->findByUser($this->user($email)) as $enrollment) {
            if ($clubSlug === $enrollment->getClub()->getSlug()) {
                return $enrollment;
            }
        }

        self::fail(\sprintf('Нет заявки %s в кружок %s', $email, $clubSlug));
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $uri, array $body = []): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($body, \JSON_THROW_ON_ERROR),
        );
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
        self::assertIsString($problem['detail']);

        return $problem;
    }

    /**
     * @return list<string> propertyPath нарушений из 422
     */
    private function violationPaths(): array
    {
        $problem = $this->assertProblem(422);
        self::assertIsArray($problem['violations'] ?? null);

        return array_column($problem['violations'], 'propertyPath');
    }
}
