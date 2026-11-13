<?php

declare(strict_types=1);

namespace App\Tests\Functional\Enrollment;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * POST /api/enrollments/{id}/cancel.
 *
 * Фикстуры: заявки Марии — swimming-dyush (confirmed) и gimnastika-voskhod (pending);
 * заявки anna.petrova@ и др. — pending в кружок Марии akvarel (Мария — владелец).
 */
final class CancelEnrollmentTest extends WebTestCase
{
    use EnrollmentApiTestTrait;

    private const string MARIA = AppFixtures::DEMO_EMAIL;
    private const string ANNA = 'anna.petrova@example.com';

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testAuthorCancelsPendingEnrollment(): void
    {
        $this->loginAs(self::MARIA);
        $id = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();

        $this->postJson('/api/enrollments/'.$id.'/cancel');

        self::assertResponseIsSuccessful();
        $enrollment = $this->responseJson();
        self::assertSame($id, $enrollment['id']);
        self::assertSame('cancelled', $enrollment['status']);
        self::assertSame('gimnastika-voskhod', $enrollment['clubId']);
        self::assertSame('Соня', $enrollment['forChild']['firstName']);
    }

    public function testAuthorCancelsConfirmedEnrollment(): void
    {
        $this->loginAs(self::MARIA);
        $id = $this->enrollmentOf(self::MARIA, 'swimming-dyush')->getId()->toRfc4122();

        $this->postJson('/api/enrollments/'.$id.'/cancel');

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->responseJson()['status']);
    }

    public function testCancellingTwiceReturns409(): void
    {
        $this->loginAs(self::MARIA);
        $id = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();

        $this->postJson('/api/enrollments/'.$id.'/cancel');
        self::assertResponseIsSuccessful();

        $this->postJson('/api/enrollments/'.$id.'/cancel');
        self::assertSame('Нельзя отменить заявку: она отменена', $this->assertProblem(409)['detail']);
    }

    public function testStrangerGets404(): void
    {
        // Анна не автор, не владелец swimming-dyush и не админ — заявку Марии она «не видит»
        $id = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();
        $this->loginAs(self::ANNA);

        $this->postJson('/api/enrollments/'.$id.'/cancel');

        self::assertSame('Заявка не найдена', $this->assertProblem(404)['detail']);
    }

    public function testClubOwnerCannotCancelIncomingEnrollment(): void
    {
        // Мария — владелец akvarel и видит входящую заявку Анны, но отменять может только автор
        $id = $this->enrollmentOf(self::ANNA, 'akvarel')->getId()->toRfc4122();
        $this->loginAs(self::MARIA);

        $this->postJson('/api/enrollments/'.$id.'/cancel');

        self::assertSame('Отменить заявку может только её автор', $this->assertProblem(403)['detail']);
    }

    public function testAdminCannotCancelSomeoneElsesEnrollment(): void
    {
        $id = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();
        $this->loginAs(AppFixtures::ADMIN_EMAIL);

        $this->postJson('/api/enrollments/'.$id.'/cancel');

        $this->assertProblem(403);
    }

    public function testUnknownOrMalformedIdReturns404(): void
    {
        $this->loginAs(self::MARIA);

        $this->postJson('/api/enrollments/'.Uuid::v7()->toRfc4122().'/cancel');
        $this->assertProblem(404);

        $this->postJson('/api/enrollments/not-a-uuid/cancel');
        $this->assertProblem(404);
    }

    public function testAnonymousRequestReturns401(): void
    {
        $id = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod')->getId()->toRfc4122();

        $this->postJson('/api/enrollments/'.$id.'/cancel');

        $this->assertProblem(401);
    }
}
