<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\DataFixtures\AppFixtures;
use App\Repository\ClubRepository;
use App\Tests\Functional\ApiTestCase;

final class ClubPublicationTest extends ApiTestCase
{
    private const string SLUG = 'swimming-dyush';

    public function testUnpublishAndPublishAgain(): void
    {
        $this->jsonRequest('PATCH', '/api/admin/clubs/'.self::SLUG, ['isPublished' => false], accessToken: $this->tokenFor(AppFixtures::ADMIN_EMAIL));

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => self::SLUG, 'name' => 'Плавание · ДЮСШ №1', 'isPublished' => false], $this->json());
        self::assertNull($this->clubs()->findOnePublishedBySlug(self::SLUG));

        $this->jsonRequest('PATCH', '/api/admin/clubs/'.self::SLUG, ['isPublished' => true], accessToken: $this->tokenFor(AppFixtures::ADMIN_EMAIL));

        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['isPublished']);
        self::assertNotNull($this->clubs()->findOnePublishedBySlug(self::SLUG));
    }

    public function testMissingFlagReturns422(): void
    {
        $this->jsonRequest('PATCH', '/api/admin/clubs/'.self::SLUG, ['name' => 'x'], accessToken: $this->tokenFor(AppFixtures::ADMIN_EMAIL));

        $problem = $this->assertProblem(422);
        self::assertNotEmpty($problem['violations'] ?? []);
    }

    public function testUnknownClubReturns404(): void
    {
        $this->jsonRequest('PATCH', '/api/admin/clubs/no-such-club', ['isPublished' => false], accessToken: $this->tokenFor(AppFixtures::ADMIN_EMAIL));

        $this->assertProblem(404);
    }

    public function testIsForbiddenForRegularUser(): void
    {
        $this->jsonRequest('PATCH', '/api/admin/clubs/'.self::SLUG, ['isPublished' => false], accessToken: $this->tokenFor(AppFixtures::DEMO_EMAIL));

        $this->assertProblem(403);
        self::assertNotNull($this->clubs()->findOnePublishedBySlug(self::SLUG));
    }

    private function clubs(): ClubRepository
    {
        $this->entityManager()->clear();

        return self::getContainer()->get(ClubRepository::class);
    }
}
