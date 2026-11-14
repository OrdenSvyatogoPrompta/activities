<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Service\Seed\DemoData;
use App\Tests\Functional\ApiTestCase;

final class EnrollmentListTest extends ApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments');

        $this->assertProblem(401);
    }

    public function testIsForbiddenForRegularUser(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments', accessToken: $this->tokenFor(DemoData::DEMO_EMAIL));

        $this->assertProblem(403);
    }

    public function testDefaultsToPendingEnrollmentsOfAllClubs(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments', accessToken: $this->tokenFor(DemoData::ADMIN_EMAIL));

        self::assertResponseIsSuccessful();
        $enrollments = $this->json();
        self::assertSame(['pending'], array_values(array_unique(array_column($enrollments, 'status'))));

        $clubIds = array_column($enrollments, 'clubId');
        self::assertContains('gimnastika-voskhod', $clubIds, 'заявка в кружок без владельца');
        self::assertContains('akvarel', $clubIds, 'заявка в кружок организатора');

        // Старые первыми
        $dates = array_column($enrollments, 'enrolledAt');
        $sorted = $dates;
        sort($sorted);
        self::assertSame($sorted, $dates);
    }

    public function testOwnerlessFilterReturnsEnrollmentsWithClubAndApplicant(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments?status=pending&ownerless=true', accessToken: $this->tokenFor(DemoData::ADMIN_EMAIL));

        self::assertResponseIsSuccessful();
        $enrollments = $this->json();
        self::assertNotEmpty($enrollments);
        foreach ($enrollments as $enrollment) {
            self::assertNull($enrollment['club']['owner'], 'в выборке только кружки без владельца');
            self::assertSame('pending', $enrollment['status']);
        }
        self::assertNotContains('akvarel', array_column($enrollments, 'clubId'));

        $sonya = $this->findByClub($enrollments, 'gimnastika-voskhod');
        self::assertSame('gimnastika-voskhod', $sonya['club']['id']);
        self::assertTrue($sonya['club']['isPublished']);
        self::assertSame($sonya['clubName'], $sonya['club']['name']);
        self::assertSame(DemoData::DEMO_EMAIL, $sonya['user']['email']);
        self::assertSame('Иванова Мария', $sonya['user']['name']);
        self::assertSame($this->user(DemoData::DEMO_EMAIL)->getId()->toRfc4122(), $sonya['user']['id']);
        self::assertSame('Соня', $sonya['forChild']['firstName']);
        self::assertSame(5, $sonya['forChild']['age']);
        self::assertSame('female', $sonya['forChild']['gender']);
        self::assertSame('2026-11-01', $sonya['enrolledAt']);
        self::assertNotNull($sonya['branchId']);
        self::assertIsString($sonya['schedule']);
        self::assertNotEmpty($sonya['days']);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $sonya['color']);
        self::assertNull($sonya['rejectReason']);
    }

    public function testOwnedClubEnrollmentsHaveOwner(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments', accessToken: $this->tokenFor(DemoData::ADMIN_EMAIL));

        $akvarel = $this->findByClub($this->json(), 'akvarel');
        self::assertSame(DemoData::DEMO_EMAIL, $akvarel['club']['owner']['email']);
        self::assertSame('Петрова Анна', $akvarel['user']['name']);
    }

    public function testStatusFilter(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments?status=confirmed&ownerless=1', accessToken: $this->tokenFor(DemoData::ADMIN_EMAIL));

        self::assertResponseIsSuccessful();
        $enrollments = $this->json();
        self::assertSame(['confirmed'], array_values(array_unique(array_column($enrollments, 'status'))));

        $artem = $this->findByClub($enrollments, 'swimming-dyush');
        self::assertSame('Артём', $artem['forChild']['firstName']);
        self::assertSame('Борисенко', $artem['branchName']);
    }

    public function testUnknownStatusReturns422(): void
    {
        $this->jsonRequest('GET', '/api/admin/enrollments?status=archived', accessToken: $this->tokenFor(DemoData::ADMIN_EMAIL));

        self::assertContains('status', $this->violationPaths());
    }

    /**
     * @param list<array<string, mixed>> $enrollments
     *
     * @return array<string, mixed>
     */
    private function findByClub(array $enrollments, string $clubId): array
    {
        foreach ($enrollments as $enrollment) {
            if ($clubId === $enrollment['clubId']) {
                return $enrollment;
            }
        }

        self::fail(\sprintf('Заявка в кружок %s не найдена в ответе', $clubId));
    }
}
