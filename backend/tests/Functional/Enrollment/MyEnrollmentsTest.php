<?php

declare(strict_types=1);

namespace App\Tests\Functional\Enrollment;

use App\DataFixtures\AppFixtures;
use App\Tests\Functional\ApiTestCase;

/**
 * Фикстуры: у Марии две заявки — Артём в swimming-dyush (confirmed, 2026-10-15)
 * и Соня в gimnastika-voskhod (pending, 2026-11-01).
 */
final class MyEnrollmentsTest extends ApiTestCase
{
    private const string MARIA = AppFixtures::DEMO_EMAIL;

    public function testReturnsOwnEnrollmentsNewestFirst(): void
    {
        $this->loginAs(self::MARIA);

        $this->client->request('GET', '/api/me/enrollments');

        self::assertResponseIsSuccessful();
        $list = $this->json();
        self::assertSame(['gimnastika-voskhod', 'swimming-dyush'], array_column($list, 'clubId'));

        $swimming = $list[1];
        self::assertSame('Плавание · ДЮСШ №1', $swimming['clubName']);
        self::assertSame('Борисенко', $swimming['branchName']);
        self::assertSame("Пн, Ср, Пт · 15:00–18:00\nВт, Чт · 10:00–12:00", $swimming['schedule']);
        self::assertSame(['Пн', 'Вт', 'Ср', 'Чт', 'Пт'], $swimming['days']);
        self::assertSame('#0ea5e9', $swimming['color']);
        self::assertSame('Артём', $swimming['forChild']['firstName']);
        self::assertSame('2026-10-15', $swimming['enrolledAt']);
        self::assertSame('confirmed', $swimming['status']);
        self::assertNull($swimming['rejectReason']);

        self::assertSame('pending', $list[0]['status']);
        self::assertSame('Соня', $list[0]['forChild']['firstName']);
    }

    public function testCancelledAreHiddenByDefaultAndAvailableByFilter(): void
    {
        $this->loginAs(self::MARIA);
        $gymnastics = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();
        $this->jsonRequest('POST', '/api/enrollments/'.$gymnastics.'/cancel', []);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/me/enrollments');
        self::assertSame(['swimming-dyush'], array_column($this->json(), 'clubId'));

        $this->client->request('GET', '/api/me/enrollments?status=cancelled');
        self::assertResponseIsSuccessful();
        $cancelled = $this->json();
        self::assertSame([$gymnastics], array_column($cancelled, 'id'));
        self::assertSame('cancelled', $cancelled[0]['status']);

        $this->client->request('GET', '/api/me/enrollments?status=confirmed,cancelled');
        self::assertSame(['gimnastika-voskhod', 'swimming-dyush'], array_column($this->json(), 'clubId'));
    }

    public function testFilterBySingleStatus(): void
    {
        $this->loginAs(self::MARIA);

        $this->client->request('GET', '/api/me/enrollments?status=pending');

        self::assertResponseIsSuccessful();
        self::assertSame(['gimnastika-voskhod'], array_column($this->json(), 'clubId'));
    }

    public function testUnknownStatusIsValidationError(): void
    {
        $this->loginAs(self::MARIA);

        $this->client->request('GET', '/api/me/enrollments?status=pending,archived');

        self::assertSame(['status'], $this->violationPaths());
    }

    public function testOnlyOwnEnrollmentsAreListed(): void
    {
        // У Игоря одна заявка «для себя» в кружок Марии akvarel
        $this->loginAs('igor.sidorov@example.com');

        $this->client->request('GET', '/api/me/enrollments');

        self::assertResponseIsSuccessful();
        $list = $this->json();
        self::assertSame(['akvarel'], array_column($list, 'clubId'));
        self::assertNull($list[0]['forChild']);
        self::assertSame('Основной', $list[0]['branchName']);
    }

    public function testUserWithoutEnrollmentsGetsEmptyList(): void
    {
        $this->loginAs(AppFixtures::ADMIN_EMAIL);

        $this->client->request('GET', '/api/me/enrollments');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json());
    }

    public function testAnonymousRequestReturns401(): void
    {
        $this->client->request('GET', '/api/me/enrollments');

        $this->assertProblem(401);
    }
}
