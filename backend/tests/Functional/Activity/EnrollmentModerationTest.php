<?php

declare(strict_types=1);

namespace App\Tests\Functional\Activity;

use App\DataFixtures\AppFixtures;
use App\Entity\Enrollment;
use App\Repository\EnrollmentRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * POST /api/enrollments/{id}/confirm и /reject (группа G).
 *
 * Данные — AppFixtures: кружок akvarel принадлежит Марии, в нём pending-заявки Анны (ребёнок
 * Маша, «Основной»), Игоря (для себя), Веры и Елены (филиал «Снеговая»). Сидовые кружки без
 * владельца: заявка Сони (дочь Марии) в gimnastika-voskhod — pending.
 */
final class EnrollmentModerationTest extends WebTestCase
{
    use ApiTestTrait;

    private const string MARIA = AppFixtures::DEMO_EMAIL;
    private const string ADMIN = AppFixtures::ADMIN_EMAIL;
    private const string ANNA = 'anna.petrova@example.com';
    private const string IGOR = 'igor.sidorov@example.com';
    private const string VERA = 'vera.kozlova@example.com';

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testOwnerConfirmsEnrollment(): void
    {
        $id = $this->enrollmentId(self::ANNA, 'akvarel');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/confirm', []);

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
        $this->api(self::MARIA, 'GET', '/api/me/activities');
        $akvarel = array_column($this->json(), null, 'name')['Акварель'];
        $incoming = array_column($akvarel['enrollments'], 'status', 'id');
        self::assertSame('confirmed', $incoming[$id]);
    }

    public function testConfirmWithoutBody(): void
    {
        $id = $this->enrollmentId(self::ANNA, 'akvarel');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/confirm');

        self::assertResponseIsSuccessful();
        self::assertSame('confirmed', $this->json()['status']);
    }

    public function testOwnerRejectsEnrollmentWithReason(): void
    {
        $id = $this->enrollmentId(self::IGOR, 'akvarel');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/reject', ['reason' => '  Группа набрана  ']);

        self::assertResponseIsSuccessful();
        $enrollment = $this->json();
        self::assertSame('rejected', $enrollment['status']);
        self::assertSame('Группа набрана', $enrollment['rejectReason']);
        self::assertNull($enrollment['forChild']);
    }

    public function testRejectWithoutReason(): void
    {
        $id = $this->enrollmentId(self::VERA, 'akvarel');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/reject', []);

        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->json()['status']);
        self::assertNull($this->json()['rejectReason']);
    }

    public function testRejectReasonIsLimited(): void
    {
        $id = $this->enrollmentId(self::VERA, 'akvarel');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/reject', ['reason' => str_repeat('я', 501)]);

        self::assertContains('reason', $this->violationPaths());
    }

    public function testInvalidTransitionReturns409(): void
    {
        $id = $this->enrollmentId(self::ANNA, 'akvarel');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/confirm', []);
        self::assertResponseIsSuccessful();

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/confirm', []);
        self::assertSame('Нельзя подтвердить заявку в статусе «Подтверждена»', $this->assertProblem(409)['detail']);

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/reject', []);
        self::assertSame('Нельзя отклонить заявку в статусе «Подтверждена»', $this->assertProblem(409)['detail']);
    }

    public function testConfirmedSeedEnrollmentCannotBeConfirmedAgain(): void
    {
        // Артём в swimming-dyush — confirmed в фикстурах, кружок без владельца
        $id = $this->enrollmentId(self::MARIA, 'swimming-dyush');

        $this->api(self::ADMIN, 'POST', '/api/enrollments/'.$id.'/confirm', []);

        $this->assertProblem(409);
    }

    public function testStrangerCannotModerate(): void
    {
        $id = $this->enrollmentId(self::ANNA, 'akvarel');

        $this->api(self::IGOR, 'POST', '/api/enrollments/'.$id.'/confirm', []);
        $problem = $this->assertProblem(403);
        self::assertSame('Подтверждать и отклонять заявку может только владелец кружка или администратор', $problem['detail']);

        $this->api(self::IGOR, 'POST', '/api/enrollments/'.$id.'/reject', []);
        $this->assertProblem(403);
    }

    public function testAuthorCannotConfirmOwnEnrollment(): void
    {
        $id = $this->enrollmentId(self::ANNA, 'akvarel');

        $this->api(self::ANNA, 'POST', '/api/enrollments/'.$id.'/confirm', []);

        $this->assertProblem(403);
    }

    public function testForbiddenWinsOverInvalidTransition(): void
    {
        // Чужому пользователю не раскрываем статус заявки: сначала права, потом переход
        $id = $this->enrollmentId(self::MARIA, 'swimming-dyush');

        $this->api(self::ANNA, 'POST', '/api/enrollments/'.$id.'/confirm', []);

        $this->assertProblem(403);
    }

    public function testOwnerlessClubIsModeratedOnlyByAdmin(): void
    {
        $id = $this->enrollmentId(self::MARIA, 'gimnastika-voskhod');

        $this->api(self::MARIA, 'POST', '/api/enrollments/'.$id.'/confirm', []);
        $this->assertProblem(403);

        $this->api(self::ADMIN, 'POST', '/api/enrollments/'.$id.'/confirm', []);
        self::assertResponseIsSuccessful();
        self::assertSame('confirmed', $this->json()['status']);
        self::assertSame('Соня', $this->json()['forChild']['firstName']);
    }

    public function testAdminCanModerateOwnedClub(): void
    {
        $id = $this->enrollmentId(self::VERA, 'akvarel');

        $this->api(self::ADMIN, 'POST', '/api/enrollments/'.$id.'/reject', ['reason' => 'Дубль']);

        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->json()['status']);
    }

    public function testUnknownEnrollmentReturns404(): void
    {
        $this->api(self::ADMIN, 'POST', '/api/enrollments/0192f000-0000-7000-8000-000000000000/confirm', []);
        self::assertSame('Заявка не найдена', $this->assertProblem(404)['detail']);

        $this->api(self::ADMIN, 'POST', '/api/enrollments/not-a-uuid/reject', []);
        $this->assertProblem(404);
    }

    public function testRequiresAuthentication(): void
    {
        $id = $this->enrollmentId(self::ANNA, 'akvarel');

        $this->api(null, 'POST', '/api/enrollments/'.$id.'/confirm', []);
        $this->assertProblem(401);

        $this->api(null, 'POST', '/api/enrollments/'.$id.'/reject', []);
        $this->assertProblem(401);
    }

    /**
     * Id единственной не отменённой заявки пользователя в кружок.
     */
    private function enrollmentId(string $email, string $clubSlug): string
    {
        $enrollments = array_values(array_filter(
            static::getContainer()->get(EnrollmentRepository::class)->findByUser($this->user($email)),
            static fn (Enrollment $enrollment): bool => $enrollment->getClub()->getSlug() === $clubSlug,
        ));
        self::assertCount(1, $enrollments, \sprintf('Ожидалась одна заявка %s в %s', $email, $clubSlug));

        return $enrollments[0]->getId()->toRfc4122();
    }
}
