<?php

declare(strict_types=1);

namespace App\Tests\Functional\Activity;

use App\Service\Seed\DemoData;
use App\Tests\Functional\ApiTestCase;

/**
 * Данные — DemoData: кружок akvarel принадлежит Марии, в нём pending-заявки Анны (ребёнок
 * Маша, «Основной»), Игоря (для себя), Веры и Елены (филиал «Снеговая»). Сидовые кружки без
 * владельца: заявка Сони (дочь Марии) в gimnastika-voskhod — pending.
 */
final class EnrollmentModerationTest extends ApiTestCase
{
    private const string MARIA = DemoData::DEMO_EMAIL;
    private const string ADMIN = DemoData::ADMIN_EMAIL;
    private const string ANNA = 'anna.petrova@example.com';
    private const string IGOR = 'igor.sidorov@example.com';
    private const string VERA = 'vera.kozlova@example.com';

    public function testOwnerConfirmsEnrollment(): void
    {
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::MARIA));

        self::assertResponseIsSuccessful();
        $enrollment = $this->json();
        self::assertSame($id, $enrollment['id']);
        self::assertSame('confirmed', $enrollment['status']);
        self::assertNull($enrollment['rejectReason']);
        self::assertSame('akvarel', $enrollment['clubId']);
        self::assertSame('Акварель', $enrollment['clubName']);
        self::assertSame('Основной', $enrollment['branchName']);
        self::assertIsString($enrollment['branchId']);
        self::assertSame('Вт, Чт, Сб · 15:30–17:30', $enrollment['schedule']);
        self::assertSame(['Вт', 'Чт', 'Сб'], $enrollment['days']);
        self::assertSame('#f59e0b', $enrollment['color']);
        self::assertSame('2026-10-20', $enrollment['enrolledAt']);
        self::assertSame('Маша', $enrollment['forChild']['firstName']);
        self::assertSame('Петрова', $enrollment['forChild']['lastName']);
        self::assertSame(7, $enrollment['forChild']['age']);
        self::assertSame('female', $enrollment['forChild']['gender']);

        // Статус виден организатору во входящих заявках активности
        $this->jsonRequest('GET', '/api/me/activities', accessToken: $this->tokenFor(self::MARIA));
        $akvarel = array_column($this->json(), null, 'name')['Акварель'];
        $incoming = array_column($akvarel['enrollments'], 'status', 'id');
        self::assertSame('confirmed', $incoming[$id]);
    }

    public function testConfirmWithoutBody(): void
    {
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', accessToken: $this->tokenFor(self::MARIA));

        self::assertResponseIsSuccessful();
        self::assertSame('confirmed', $this->json()['status']);
    }

    public function testOwnerRejectsEnrollmentWithReason(): void
    {
        $id = $this->enrollmentOf(self::IGOR, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', ['reason' => '  Группа набрана  '], accessToken: $this->tokenFor(self::MARIA));

        self::assertResponseIsSuccessful();
        $enrollment = $this->json();
        self::assertSame('rejected', $enrollment['status']);
        self::assertSame('Группа набрана', $enrollment['rejectReason']);
        self::assertNull($enrollment['forChild']);
    }

    public function testRejectWithoutReason(): void
    {
        $id = $this->enrollmentOf(self::VERA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', [], accessToken: $this->tokenFor(self::MARIA));

        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->json()['status']);
        self::assertNull($this->json()['rejectReason']);
    }

    public function testRejectReasonIsLimited(): void
    {
        $id = $this->enrollmentOf(self::VERA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', ['reason' => str_repeat('я', 501)], accessToken: $this->tokenFor(self::MARIA));

        self::assertContains('reason', $this->violationPaths());
    }

    public function testInvalidTransitionReturns409(): void
    {
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::MARIA));
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::MARIA));
        self::assertSame('Нельзя подтвердить заявку: она подтверждена', $this->assertProblem(409)['detail']);

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', [], accessToken: $this->tokenFor(self::MARIA));
        self::assertSame('Нельзя отклонить заявку: она подтверждена', $this->assertProblem(409)['detail']);
    }

    public function testStrangerGets404(): void
    {
        // Игорь не автор заявки Анны, не владелец akvarel — заявку он «не видит»
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::IGOR));
        self::assertSame('Заявка не найдена', $this->assertProblem(404)['detail']);

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', [], accessToken: $this->tokenFor(self::IGOR));
        $this->assertProblem(404);
    }

    public function testStrangerGets404EvenForInvalidTransition(): void
    {
        $id = $this->enrollmentOf(self::MARIA, 'swimming-dyush')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::ANNA));

        $this->assertProblem(404);
    }

    public function testAuthorCannotConfirmOwnEnrollment(): void
    {
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::ANNA));

        $problem = $this->assertProblem(403);
        self::assertSame('Подтверждать и отклонять заявку может только владелец кружка', $problem['detail']);
    }

    public function testForbiddenWinsOverInvalidTransition(): void
    {
        // Автор видит заявку, но не модерирует её: 403 раньше, чем 409 за статус confirmed
        $id = $this->enrollmentOf(self::MARIA, 'swimming-dyush')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::MARIA));

        $this->assertProblem(403);
    }

    public function testOwnerlessClubEnrollmentCannotBeModerated(): void
    {
        $id = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::MARIA));
        $this->assertProblem(403);

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', [], accessToken: $this->tokenFor(self::ADMIN));
        $this->assertProblem(404);
    }

    public function testAdminCannotModerateSomeoneElsesClub(): void
    {
        // Заявки обрабатывает только организатор: для админа чужая заявка — как для постороннего
        $id = $this->enrollmentOf(self::VERA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', ['reason' => 'Дубль'], accessToken: $this->tokenFor(self::ADMIN));

        $this->assertProblem(404);
    }

    public function testUnknownEnrollmentReturns404(): void
    {
        $this->jsonRequest('POST', '/api/enrollments/0192f000-0000-7000-8000-000000000000/confirm', [], accessToken: $this->tokenFor(self::MARIA));
        self::assertSame('Заявка не найдена', $this->assertProblem(404)['detail']);

        $this->jsonRequest('POST', '/api/enrollments/not-a-uuid/reject', [], accessToken: $this->tokenFor(self::MARIA));
        $this->assertProblem(404);
    }

    public function testRequiresAuthentication(): void
    {
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/confirm', []);
        $this->assertProblem(401);

        $this->jsonRequest('POST', '/api/enrollments/'.$id.'/reject', []);
        $this->assertProblem(401);
    }
}
