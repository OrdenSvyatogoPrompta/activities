<?php

declare(strict_types=1);

namespace App\Tests\Functional\Enrollment;

use App\DataFixtures\AppFixtures;
use App\Entity\Branch;
use App\Entity\Club;
use App\Entity\Enum\Category;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * POST /api/enrollments.
 *
 * Фикстуры: у Марии дети Артём (подтверждённая заявка в swimming-dyush, филиал «Борисенко»)
 * и Соня (заявка pending в gimnastika-voskhod); у swimming-dyush три филиала,
 * у akvarell — один «Основной». Ребёнок Маша — у anna.petrova@example.com.
 */
final class CreateEnrollmentTest extends WebTestCase
{
    use EnrollmentApiTestTrait;

    private const string MARIA = AppFixtures::DEMO_EMAIL;
    private const string ANNA = 'anna.petrova@example.com';

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testChildEnrollmentInSingleBranchClubGetsBranchSubstituted(): void
    {
        $this->loginAs(self::MARIA);
        $artem = $this->child(self::MARIA, 'Артём');
        $branch = $this->branch('akvarell', 'Основной');

        $this->postJson('/api/enrollments', ['clubId' => 'akvarell', 'childId' => $artem->getId()->toRfc4122()]);

        self::assertResponseStatusCodeSame(201);
        $enrollment = $this->responseJson();
        self::assertTrue(Uuid::isValid($enrollment['id']));
        self::assertSame('akvarell', $enrollment['clubId']);
        self::assertSame('Акварель', $enrollment['clubName']);
        self::assertSame($branch->getId()->toRfc4122(), $enrollment['branchId']);
        self::assertSame('Основной', $enrollment['branchName']);
        self::assertSame("Вт, Чт · 15:30–17:30\nСб · 10:00–12:00", $enrollment['schedule']);
        self::assertSame(['Вт', 'Чт', 'Сб'], $enrollment['days']);
        self::assertSame('#f59e0b', $enrollment['color']);
        self::assertSame([
            'id' => $artem->getId()->toRfc4122(),
            'firstName' => 'Артём',
            'lastName' => 'Иванов',
            'age' => 8,
            'gender' => 'male',
        ], $enrollment['forChild']);
        self::assertSame((new \DateTimeImmutable())->format('Y-m-d'), $enrollment['enrolledAt']);
        self::assertSame('pending', $enrollment['status']);
        self::assertArrayHasKey('rejectReason', $enrollment);
        self::assertNull($enrollment['rejectReason']);
    }

    public function testSelfEnrollmentWithExplicitBranch(): void
    {
        $this->loginAs(self::MARIA);
        $branch = $this->branch('swimming-dyush', 'Снеговая');

        $this->postJson('/api/enrollments', ['clubId' => 'swimming-dyush', 'branchId' => $branch->getId()->toRfc4122()]);

        self::assertResponseStatusCodeSame(201);
        $enrollment = $this->responseJson();
        self::assertSame('Снеговая', $enrollment['branchName']);
        self::assertSame('Вт, Чт, Сб · 09:00–12:00', $enrollment['schedule']);
        self::assertSame(['Вт', 'Чт', 'Сб'], $enrollment['days']);
        self::assertNull($enrollment['forChild']);
        self::assertSame('pending', $enrollment['status']);
    }

    public function testCreatedEnrollmentAppearsInOwnList(): void
    {
        $this->loginAs(self::MARIA);
        $this->postJson('/api/enrollments', ['clubId' => 'akvarell']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseJson()['id'];

        $this->client->request('GET', '/api/me/enrollments');

        self::assertResponseIsSuccessful();
        self::assertContains($id, array_column($this->responseJson(), 'id'));
    }

    public function testBranchIsRequiredWhenClubHasSeveralBranches(): void
    {
        $this->loginAs(self::MARIA);

        $this->postJson('/api/enrollments', ['clubId' => 'swimming-dyush']);

        self::assertSame(['branchId'], $this->violationPaths());
    }

    public function testBranchOfAnotherClubIsRejected(): void
    {
        $this->loginAs(self::MARIA);
        $foreignBranch = $this->branch('akvarell', 'Основной');

        $this->postJson('/api/enrollments', ['clubId' => 'swimming-dyush', 'branchId' => $foreignBranch->getId()->toRfc4122()]);

        self::assertSame(['branchId'], $this->violationPaths());
    }

    public function testChildOfAnotherUserIsRejected(): void
    {
        $this->loginAs(self::MARIA);
        $foreignChild = $this->child(self::ANNA, 'Маша');

        $this->postJson('/api/enrollments', ['clubId' => 'akvarell', 'childId' => $foreignChild->getId()->toRfc4122()]);

        self::assertSame(['childId'], $this->violationPaths());
    }

    public function testMalformedIdsAndMissingClubAreValidationErrors(): void
    {
        $this->loginAs(self::MARIA);

        $this->postJson('/api/enrollments', ['branchId' => 'not-a-uuid', 'childId' => 'nope']);

        $paths = $this->violationPaths();
        sort($paths);
        self::assertSame(['branchId', 'childId', 'clubId'], $paths);
    }

    public function testUnknownClubReturns404(): void
    {
        $this->loginAs(self::MARIA);

        $this->postJson('/api/enrollments', ['clubId' => 'no-such-club']);

        self::assertSame('Кружок не найден', $this->assertProblem(404)['detail']);
    }

    public function testUnpublishedClubReturns404(): void
    {
        $this->loginAs(self::MARIA);
        $club = new Club(
            slug: 'hidden-club',
            name: 'Скрытый кружок',
            categories: [Category::Science],
            emoji: '🔒',
            color: '#6366f1',
            shortDescription: 'Не опубликован',
            fullDescription: 'Не опубликован',
            ageMin: 6,
            ageMax: 12,
            visitType: VisitType::Recurring,
            priceType: PriceType::Free,
            priceAmount: 0,
            isPublished: false,
        );
        $club->addBranch(new Branch(Branch::DEFAULT_NAME, 'ул. Светланская, 1'));
        $this->entityManager()->persist($club);
        $this->entityManager()->flush();

        $this->postJson('/api/enrollments', ['clubId' => 'hidden-club']);

        $this->assertProblem(404);
    }

    public function testDuplicateActiveEnrollmentOfChildReturns409(): void
    {
        $this->loginAs(self::MARIA);
        // У Артёма уже подтверждённая заявка в «Борисенко»; другой филиал того же кружка — тоже дубль
        $artem = $this->child(self::MARIA, 'Артём');
        $branch = $this->branch('swimming-dyush', 'Снеговая');

        $this->postJson('/api/enrollments', [
            'clubId' => 'swimming-dyush',
            'branchId' => $branch->getId()->toRfc4122(),
            'childId' => $artem->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('Артём', $this->assertProblem(409)['detail']);
    }

    public function testDuplicateSelfEnrollmentReturns409(): void
    {
        $this->loginAs(self::MARIA);

        $this->postJson('/api/enrollments', ['clubId' => 'akvarell']);
        self::assertResponseStatusCodeSame(201);

        $this->postJson('/api/enrollments', ['clubId' => 'akvarell']);
        $this->assertProblem(409);
    }

    public function testSelfAndChildAreDifferentParticipants(): void
    {
        $this->loginAs(self::MARIA);
        $sonya = $this->child(self::MARIA, 'Соня');

        // У Сони pending-заявка в гимнастику, сама Мария туда не записана
        $this->postJson('/api/enrollments', ['clubId' => 'gimnastika-voskhod']);
        self::assertResponseStatusCodeSame(201);

        $this->postJson('/api/enrollments', ['clubId' => 'gimnastika-voskhod', 'childId' => $sonya->getId()->toRfc4122()]);
        $this->assertProblem(409);
    }

    public function testCanEnrollAgainAfterCancellation(): void
    {
        $this->loginAs(self::MARIA);
        $sonya = $this->child(self::MARIA, 'Соня');
        $existing = $this->enrollmentOf(self::MARIA, 'gimnastika-voskhod');

        $this->postJson('/api/enrollments/'.$existing->getId()->toRfc4122().'/cancel');
        self::assertResponseIsSuccessful();

        $this->postJson('/api/enrollments', ['clubId' => 'gimnastika-voskhod', 'childId' => $sonya->getId()->toRfc4122()]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('pending', $this->responseJson()['status']);
    }

    public function testMalformedJsonReturns400(): void
    {
        $this->loginAs(self::MARIA);

        $this->client->request('POST', '/api/enrollments', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        $this->assertProblem(400);
    }

    public function testAnonymousRequestReturns401(): void
    {
        $this->postJson('/api/enrollments', ['clubId' => 'akvarell']);

        $this->assertProblem(401);
    }
}
