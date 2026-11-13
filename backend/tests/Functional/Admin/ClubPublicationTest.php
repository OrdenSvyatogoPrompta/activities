<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Repository\ClubRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Группа H: снятие кружка с публикации (`PATCH /api/admin/clubs/{slug}`).
 */
final class ClubPublicationTest extends WebTestCase
{
    use AdminApiTestTrait;

    private const string SLUG = 'swimming-dyush';

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testUnpublishAndPublishAgain(): void
    {
        $this->requestAs($this->admin(), 'PATCH', '/api/admin/clubs/'.self::SLUG, ['isPublished' => false]);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => self::SLUG, 'name' => 'Плавание · ДЮСШ №1', 'isPublished' => false], $this->responseJson());
        self::assertNull($this->clubs()->findOnePublishedBySlug(self::SLUG));

        $this->requestAs($this->admin(), 'PATCH', '/api/admin/clubs/'.self::SLUG, ['isPublished' => true]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseJson()['isPublished']);
        self::assertNotNull($this->clubs()->findOnePublishedBySlug(self::SLUG));
    }

    public function testMissingFlagReturns422(): void
    {
        $this->requestAs($this->admin(), 'PATCH', '/api/admin/clubs/'.self::SLUG, ['name' => 'x']);

        $problem = $this->assertProblem(422);
        self::assertNotEmpty($problem['violations'] ?? []);
    }

    public function testUnknownClubReturns404(): void
    {
        $this->requestAs($this->admin(), 'PATCH', '/api/admin/clubs/no-such-club', ['isPublished' => false]);

        $this->assertProblem(404);
    }

    public function testIsForbiddenForRegularUser(): void
    {
        $this->requestAs($this->demoUser(), 'PATCH', '/api/admin/clubs/'.self::SLUG, ['isPublished' => false]);

        $this->assertProblem(403);
        self::assertNotNull($this->clubs()->findOnePublishedBySlug(self::SLUG));
    }

    private function clubs(): ClubRepository
    {
        $this->em()->clear();

        return self::getContainer()->get(ClubRepository::class);
    }
}
