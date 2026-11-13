<?php

declare(strict_types=1);

namespace App\Tests\Functional\Activity;

use App\DataFixtures\AppFixtures;
use App\Entity\Branch;
use App\Repository\ClubRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET/POST /api/me/activities, GET /api/me/activities/{id} (группа G).
 *
 * Данные — AppFixtures: у Марии одобренная «Акварель» (кружок akvarel, филиалы «Основной»
 * и «Снеговая», 4 входящие заявки) и «Настольные игры» на модерации.
 */
final class MyActivitiesTest extends WebTestCase
{
    use ApiTestTrait;

    private const string MARIA = AppFixtures::DEMO_EMAIL;
    private const string ANNA = 'anna.petrova@example.com';

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testListRequiresAuthentication(): void
    {
        $this->api(null, 'GET', '/api/me/activities');

        $this->assertProblem(401);
    }

    public function testListReturnsOwnActivitiesOfAllStatuses(): void
    {
        $this->api(self::MARIA, 'GET', '/api/me/activities');

        self::assertResponseIsSuccessful();
        $activities = $this->byName($this->json());
        self::assertArrayHasKey('Акварель', $activities);
        self::assertArrayHasKey(AppFixtures::PENDING_ACTIVITY_NAME, $activities);

        $pending = $activities[AppFixtures::PENDING_ACTIVITY_NAME];
        self::assertSame('pending', $pending['status']);
        self::assertNull($pending['clubId']);
        self::assertNull($pending['moderationComment']);
        self::assertSame([], $pending['enrollments']);
        self::assertSame('1 200 ₽ / мес', $pending['price']);
    }

    public function testApprovedActivityHasBranchesAndIncomingEnrollments(): void
    {
        $this->api(self::MARIA, 'GET', '/api/me/activities');

        self::assertResponseIsSuccessful();
        $akvarel = $this->byName($this->json())['Акварель'];

        self::assertSame('approved', $akvarel['status']);
        self::assertSame('akvarel', $akvarel['clubId']);
        self::assertSame('Творчество', $akvarel['category']);
        self::assertSame('🎨', $akvarel['emoji']);
        self::assertSame(5, $akvarel['ageMin']);
        self::assertSame(14, $akvarel['ageMax']);
        self::assertSame('5–14 лет', $akvarel['age']);
        self::assertSame('both', $akvarel['visitType']);
        self::assertSame('pushkin', $akvarel['priceType']);
        self::assertSame(2500, $akvarel['priceAmount']);
        self::assertSame('2 500 ₽ / мес', $akvarel['price']);
        self::assertSame(['рисование'], $akvarel['tags']);

        // Филиалы активности: совместимые days/timeFrom/timeTo + slots
        self::assertCount(2, $akvarel['branches']);
        [$main, $snegovaya] = $akvarel['branches'];
        self::assertSame('Основной', $main['name']);
        self::assertSame('пр. Океанский, 44', $main['address']);
        self::assertSame('+7 423 243-55-20', $main['phone']);
        self::assertSame(['Вт', 'Чт', 'Сб'], $main['days']);
        self::assertSame('15:30', $main['timeFrom']);
        self::assertSame('17:30', $main['timeTo']);
        self::assertSame('Вт, Чт, Сб · 15:30–17:30', $main['schedule']);
        self::assertSame(['day' => 'Вт', 'timeFrom' => '15:30', 'timeTo' => '17:30'], $main['slots'][0]);
        self::assertCount(3, $main['slots']);
        self::assertSame('Снеговая', $snegovaya['name']);
        self::assertNull($snegovaya['phone']);
        self::assertSame(['Ср', 'Пт'], $snegovaya['days']);

        // Входящие заявки: branchId — филиал кружка, activityBranchId — филиал активности
        $clubBranchIds = $this->clubBranchIdsByName('akvarel');
        $activityBranchIds = ['Основной' => $main['id'], 'Снеговая' => $snegovaya['id']];

        $enrollments = $akvarel['enrollments'];
        self::assertCount(4, $enrollments);
        self::assertSame(['Петрова Анна', 'Сидоров Игорь', 'Козлова Вера', 'Новикова Елена'], array_column($enrollments, 'personName'));
        self::assertSame(['Маша', null, 'Дима', 'Катя'], array_column($enrollments, 'childName'));
        self::assertSame('2026-10-20', $enrollments[0]['enrolledAt']);
        foreach ($enrollments as $enrollment) {
            self::assertSame('pending', $enrollment['status']);
            self::assertSame($clubBranchIds[$enrollment['branchName']], $enrollment['branchId']);
            self::assertSame($activityBranchIds[$enrollment['branchName']], $enrollment['activityBranchId']);
            self::assertNotSame($enrollment['branchId'], $enrollment['activityBranchId']);
        }
    }

    public function testOtherUserDoesNotSeeForeignActivities(): void
    {
        $this->api(self::ANNA, 'GET', '/api/me/activities');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json());
    }

    public function testShowOwnActivity(): void
    {
        $id = $this->activityId('Акварель');

        $this->api(self::MARIA, 'GET', '/api/me/activities/'.$id);

        self::assertResponseIsSuccessful();
        $activity = $this->json();
        self::assertSame($id, $activity['id']);
        self::assertSame('akvarel', $activity['clubId']);
        self::assertCount(4, $activity['enrollments']);
    }

    public function testShowForeignActivityReturns404(): void
    {
        $id = $this->activityId('Акварель');

        $this->api(self::ANNA, 'GET', '/api/me/activities/'.$id);

        self::assertSame('Активность не найдена', $this->assertProblem(404)['detail']);
    }

    public function testShowUnknownOrMalformedIdReturns404(): void
    {
        $this->api(self::MARIA, 'GET', '/api/me/activities/0192f000-0000-7000-8000-000000000000');
        $this->assertProblem(404);

        $this->api(self::MARIA, 'GET', '/api/me/activities/not-a-uuid');
        $this->assertProblem(404);
    }

    public function testCreateActivity(): void
    {
        $this->api(self::ANNA, 'POST', '/api/me/activities', $this->validPayload());

        self::assertResponseStatusCodeSame(201);
        $activity = $this->json();
        self::assertSame('Шахматы', $activity['name']);
        self::assertSame('Наука', $activity['category']);
        self::assertSame('♟️', $activity['emoji']);
        self::assertSame('pending', $activity['status']);
        self::assertNull($activity['clubId']);
        self::assertNull($activity['moderationComment']);
        self::assertSame(1500, $activity['priceAmount']);
        self::assertSame('paid', $activity['priceType']);
        self::assertSame(['шахматы', 'логика'], $activity['tags']);
        self::assertSame([], $activity['enrollments']);

        self::assertCount(1, $activity['branches']);
        $branch = $activity['branches'][0];
        self::assertSame('Центр', $branch['name']);
        self::assertSame('ул. Светланская, 1', $branch['address']);
        self::assertSame(['Пн', 'Ср'], $branch['days']);
        self::assertSame('10:00', $branch['timeFrom']);
        self::assertSame('11:00', $branch['timeTo']);
        self::assertSame("Пн · 10:00–11:00\nСр · 16:00–17:30", $branch['schedule']);

        // Появилась в своём списке и доступна по id
        $this->api(self::ANNA, 'GET', '/api/me/activities/'.$activity['id']);
        self::assertResponseIsSuccessful();
        self::assertSame('pending', $this->json()['status']);
    }

    public function testCreateFreeActivityForcesZeroPriceAndDefaults(): void
    {
        $payload = $this->validPayload();
        $payload['priceType'] = 'free';
        $payload['priceAmount'] = 999;
        unset($payload['emoji'], $payload['tags'], $payload['branches'][0]['name'], $payload['branches'][0]['phone']);

        $this->api(self::ANNA, 'POST', '/api/me/activities', $payload);

        self::assertResponseStatusCodeSame(201);
        $activity = $this->json();
        self::assertSame(0, $activity['priceAmount']);
        self::assertSame('Бесплатно', $activity['price']);
        self::assertSame('🎯', $activity['emoji']);
        self::assertSame([], $activity['tags']);
        self::assertSame('Основной', $activity['branches'][0]['name']);
        self::assertNull($activity['branches'][0]['phone']);
    }

    public function testCreateValidatesFields(): void
    {
        $payload = $this->validPayload();
        $payload['name'] = 'A';
        $payload['category'] = 'Кулинария';
        $payload['ageMin'] = 14;
        $payload['ageMax'] = 5;
        $payload['visitType'] = 'sometimes';
        $payload['branches'][0]['address'] = ' ';
        $payload['branches'][0]['slots'][0]['timeTo'] = '09:00';
        $payload['branches'][0]['slots'][1]['day'] = 'Mon';
        $payload['branches'][0]['slots'][1]['timeFrom'] = '25:00';

        $this->api(self::ANNA, 'POST', '/api/me/activities', $payload);

        $paths = $this->violationPaths();
        foreach ([
            'name',
            'category',
            'ageMax',
            'visitType',
            'branches[0].address',
            'branches[0].slots[0].timeTo',
            'branches[0].slots[1].day',
            'branches[0].slots[1].timeFrom',
        ] as $path) {
            self::assertContains($path, $paths);
        }
    }

    public function testCreateRequiresBranchesSlotsAndPriceForPaid(): void
    {
        $payload = $this->validPayload();
        unset($payload['priceAmount'], $payload['description']);
        $payload['branches'][] = ['address' => 'ул. Фонтанная, 2', 'slots' => []];

        $this->api(self::ANNA, 'POST', '/api/me/activities', $payload);

        $paths = $this->violationPaths();
        self::assertContains('priceAmount', $paths);
        self::assertContains('description', $paths);
        self::assertContains('branches[1].slots', $paths);

        $payload = $this->validPayload();
        $payload['branches'] = [];
        $this->api(self::ANNA, 'POST', '/api/me/activities', $payload);

        self::assertContains('branches', $this->violationPaths());
    }

    public function testCreateWithEmptyBodyReturnsRussianViolations(): void
    {
        $this->api(self::ANNA, 'POST', '/api/me/activities', []);

        $problem = $this->assertProblem(422);
        $messages = array_column($problem['violations'], 'message', 'propertyPath');
        self::assertSame('Укажите название', $messages['name'] ?? null);
        self::assertSame('Добавьте хотя бы один филиал', $messages['branches'] ?? null);
    }

    public function testCreateWithMalformedJsonReturns400(): void
    {
        $this->client->request('POST', '/api/me/activities', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor(self::ANNA),
            'CONTENT_TYPE' => 'application/json',
        ], content: '{');

        $this->assertProblem(400);
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->api(null, 'POST', '/api/me/activities', $this->validPayload());

        $this->assertProblem(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Шахматы',
            'category' => 'Наука',
            'emoji' => '♟️',
            'description' => 'Шахматный клуб для детей. Турниры по выходным.',
            'ageMin' => 6,
            'ageMax' => 16,
            'visitType' => 'recurring',
            'priceType' => 'paid',
            'priceAmount' => 1500,
            'tags' => ['шахматы', ' логика ', 'шахматы'],
            'branches' => [[
                'name' => 'Центр',
                'address' => 'ул. Светланская, 1',
                'phone' => '+7 423 200-00-00',
                'slots' => [
                    ['day' => 'Ср', 'timeFrom' => '16:00', 'timeTo' => '17:30'],
                    ['day' => 'Пн', 'timeFrom' => '10:00', 'timeTo' => '11:00'],
                ],
            ]],
        ];
    }

    /**
     * @param array<mixed> $activities
     *
     * @return array<string, array<string, mixed>>
     */
    private function byName(array $activities): array
    {
        return array_column($activities, null, 'name');
    }

    private function activityId(string $name): string
    {
        $this->api(self::MARIA, 'GET', '/api/me/activities');
        self::assertResponseIsSuccessful();

        return $this->byName($this->json())[$name]['id'];
    }

    /**
     * @return array<string, string> id филиалов кружка по имени
     */
    private function clubBranchIdsByName(string $slug): array
    {
        $club = static::getContainer()->get(ClubRepository::class)->findOneBySlugWithBranches($slug);
        self::assertNotNull($club);

        $ids = [];
        foreach ($club->getBranches() as $branch) {
            \assert($branch instanceof Branch);
            $ids[$branch->getName()] = $branch->getId()->toRfc4122();
        }

        return $ids;
    }
}
