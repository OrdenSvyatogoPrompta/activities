<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Club;
use App\Entity\Enum\ActivityStatus;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\ScheduleSlot;
use App\Repository\ActivityRepository;
use App\Repository\ClubRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Группа H: очередь модерации, одобрение и отклонение активностей.
 */
final class ActivityModerationTest extends WebTestCase
{
    use AdminApiTestTrait;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testListRequiresAuthentication(): void
    {
        $this->requestAs(null, 'GET', '/api/admin/activities');

        $this->assertProblem(401);
    }

    public function testListIsForbiddenForRegularUser(): void
    {
        $this->requestAs($this->demoUser(), 'GET', '/api/admin/activities');

        $this->assertProblem(403);
    }

    public function testListReturnsPendingActivitiesByDefault(): void
    {
        $this->requestAs($this->admin(), 'GET', '/api/admin/activities');

        self::assertResponseIsSuccessful();
        $activities = $this->responseJson();
        self::assertNotEmpty($activities);
        self::assertSame(['pending'], array_values(array_unique(array_column($activities, 'status'))));

        $activity = $this->findByName($activities, AppFixtures::PENDING_ACTIVITY_NAME);
        self::assertSame('Наука', $activity['category']);
        self::assertSame('🎲', $activity['emoji']);
        self::assertNull($activity['clubId']);
        self::assertNull($activity['moderationComment']);
        self::assertSame(6, $activity['ageMin']);
        self::assertSame(16, $activity['ageMax']);
        self::assertSame('both', $activity['visitType']);
        self::assertSame('paid', $activity['priceType']);
        self::assertSame(1200, $activity['priceAmount']);
        self::assertSame(['настольные игры', 'логика'], $activity['tags']);
        self::assertSame([], $activity['enrollments']);

        self::assertSame([
            'id' => $this->demoUser()->getId()->toRfc4122(),
            'name' => 'Иванова Мария',
            'email' => AppFixtures::DEMO_EMAIL,
        ], $activity['owner']);

        self::assertCount(1, $activity['branches']);
        $branch = $activity['branches'][0];
        self::assertSame('Основной', $branch['name']);
        self::assertSame('ул. Фокина, 12', $branch['address']);
        self::assertSame('+7 924 111-22-33', $branch['phone']);
        self::assertSame(['Сб'], $branch['days']);
        self::assertSame('12:00', $branch['timeFrom']);
        self::assertSame('14:00', $branch['timeTo']);
        self::assertSame([['day' => 'Сб', 'timeFrom' => '12:00', 'timeTo' => '14:00']], $branch['slots']);
    }

    public function testListByStatusApprovedIncludesClubAndIncomingEnrollments(): void
    {
        $this->requestAs($this->admin(), 'GET', '/api/admin/activities', query: ['status' => 'approved']);

        self::assertResponseIsSuccessful();
        $activity = $this->findByName($this->responseJson(), 'Акварель');
        self::assertSame('approved', $activity['status']);
        self::assertSame('akvarel', $activity['clubId']);
        self::assertCount(2, $activity['branches']);
        self::assertCount(4, $activity['enrollments']);

        $first = $activity['enrollments'][0];
        self::assertSame('Петрова Анна', $first['personName']);
        self::assertSame('Маша', $first['childName']);
        self::assertSame('Основной', $first['branchName']);
        self::assertSame('2026-10-20', $first['enrolledAt']);
        self::assertSame('pending', $first['status']);

        $club = $this->clubs()->findOneBySlugWithBranches('akvarel');
        self::assertSame($club->getFirstBranch()->getId()->toRfc4122(), $first['branchId']);

        $self = $activity['enrollments'][1];
        self::assertSame('Сидоров Игорь', $self['personName']);
        self::assertNull($self['childName']);
    }

    public function testListWithUnknownStatusReturns422(): void
    {
        $this->requestAs($this->admin(), 'GET', '/api/admin/activities', query: ['status' => 'archived']);

        $problem = $this->assertProblem(422);
        self::assertContains('status', self::violationPaths($problem));
    }

    public function testApprovePublishesClubOwnedByAuthor(): void
    {
        $id = $this->pendingActivityId();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/approve");

        self::assertResponseIsSuccessful();
        $activity = $this->responseJson();
        self::assertSame($id, $activity['id']);
        self::assertSame('approved', $activity['status']);
        self::assertSame('nastolnyye-igry', $activity['clubId']);
        self::assertSame(AppFixtures::DEMO_EMAIL, $activity['owner']['email']);
        self::assertSame([], $activity['enrollments']);

        $club = $this->clubs()->findOneBySlugWithBranches('nastolnyye-igry');
        self::assertInstanceOf(Club::class, $club);
        self::assertTrue($club->isPublished());
        self::assertSame(AppFixtures::PENDING_ACTIVITY_NAME, $club->getName());
        self::assertSame([Category::Science], $club->getCategories());
        self::assertSame('#6366f1', $club->getColor());
        self::assertSame('🎲', $club->getEmoji());
        self::assertSame('Клуб настольных игр для детей и родителей.', $club->getShortDescription());
        self::assertStringStartsWith('Клуб настольных игр для детей и родителей. Стратегии', $club->getFullDescription());
        self::assertSame(6, $club->getAgeMin());
        self::assertSame(16, $club->getAgeMax());
        self::assertSame(VisitType::Both, $club->getVisitType());
        self::assertSame(PriceType::Paid, $club->getPriceType());
        self::assertSame(1200, $club->getPriceAmount());
        self::assertSame(['настольные игры', 'логика'], $club->getTags());
        self::assertSame(AppFixtures::DEMO_EMAIL, $club->getOwner()?->getEmail());

        $source = $club->getSourceActivity();
        self::assertNotNull($source);
        self::assertSame($id, $source->getId()->toRfc4122());
        self::assertSame(ActivityStatus::Approved, $source->getStatus());
        self::assertSame(AppFixtures::ADMIN_EMAIL, $source->getModeratedBy()?->getEmail());
        self::assertNotNull($source->getModeratedAt());

        self::assertCount(1, $club->getBranches());
        $branch = $club->getFirstBranch();
        self::assertSame('Основной', $branch->getName());
        self::assertSame('ул. Фокина, 12', $branch->getAddress());
        self::assertSame('+7 924 111-22-33', $branch->getPhone());
        self::assertSame($source->getBranches()->first()->getId()->toRfc4122(), $branch->getSourceActivityBranch()?->getId()->toRfc4122());
        self::assertCount(1, $branch->getSlots());
        self::assertSame(DayOfWeek::Saturday, $branch->getSlots()[0]->getDay());
        self::assertSame('12:00', $branch->getSlots()[0]->getTimeFromString());
        self::assertSame('14:00', $branch->getSlots()[0]->getTimeToString());

        // Одобренная активность уходит из очереди модерации
        $this->requestAs($this->admin(), 'GET', '/api/admin/activities');
        self::assertNotContains($id, array_column($this->responseJson(), 'id'));
    }

    public function testApproveGeneratesUniqueSlug(): void
    {
        $id = $this->createPendingActivity('Акварель');

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/approve");

        self::assertResponseIsSuccessful();
        self::assertSame('akvarel-2', $this->responseJson()['clubId']);
    }

    public function testApproveTwiceReturns409(): void
    {
        $id = $this->pendingActivityId();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/approve");
        self::assertResponseIsSuccessful();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/approve");
        $problem = $this->assertProblem(409);
        self::assertSame('Модерировать можно только активность на рассмотрении', $problem['detail']);
        self::assertCount(1, array_filter(
            $this->clubs()->findByOwner($this->demoUser()),
            static fn (Club $club): bool => AppFixtures::PENDING_ACTIVITY_NAME === $club->getName(),
        ));
    }

    public function testApproveRejectedActivityReturns409(): void
    {
        $id = $this->pendingActivityId();
        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/reject", ['comment' => 'Нет адреса']);
        self::assertResponseIsSuccessful();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/approve");

        $this->assertProblem(409);
    }

    public function testApproveUnknownActivityReturns404(): void
    {
        $this->requestAs($this->admin(), 'POST', '/api/admin/activities/0190f2a0-0000-7000-8000-000000000000/approve');
        $this->assertProblem(404);

        $this->requestAs($this->admin(), 'POST', '/api/admin/activities/not-a-uuid/approve');
        $this->assertProblem(404);
    }

    public function testApproveIsForbiddenForRegularUser(): void
    {
        $id = $this->pendingActivityId();

        // Автор не может одобрить собственную активность
        $this->requestAs($this->demoUser(), 'POST', "/api/admin/activities/$id/approve");

        $this->assertProblem(403);
        self::assertTrue($this->activities()->findOneWithDetails($id)->isPending());
    }

    public function testRejectStoresComment(): void
    {
        $id = $this->pendingActivityId();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/reject", ['comment' => '  Укажите точный адрес филиала  ']);

        self::assertResponseIsSuccessful();
        $activity = $this->responseJson();
        self::assertSame('rejected', $activity['status']);
        self::assertSame('Укажите точный адрес филиала', $activity['moderationComment']);
        self::assertNull($activity['clubId']);
        self::assertSame(AppFixtures::DEMO_EMAIL, $activity['owner']['email']);

        $entity = $this->activities()->findOneWithDetails($id);
        self::assertSame(ActivityStatus::Rejected, $entity->getStatus());
        self::assertSame(AppFixtures::ADMIN_EMAIL, $entity->getModeratedBy()?->getEmail());
        self::assertNull($entity->getClub());
        self::assertFalse($this->clubs()->slugExists('nastolnyye-igry'));

        $this->requestAs($this->admin(), 'GET', '/api/admin/activities', query: ['status' => 'rejected']);
        self::assertContains($id, array_column($this->responseJson(), 'id'));
    }

    public function testRejectWithoutCommentReturns422(): void
    {
        $id = $this->pendingActivityId();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/reject", []);
        self::assertContains('comment', self::violationPaths($this->assertProblem(422)));

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/reject", ['comment' => '   ']);
        self::assertContains('comment', self::violationPaths($this->assertProblem(422)));

        self::assertTrue($this->activities()->findOneWithDetails($id)->isPending());
    }

    public function testRejectApprovedActivityReturns409(): void
    {
        $approved = $this->activities()->findByStatus(ActivityStatus::Approved);
        self::assertNotEmpty($approved);
        $id = $approved[0]->getId()->toRfc4122();

        $this->requestAs($this->admin(), 'POST', "/api/admin/activities/$id/reject", ['comment' => 'Передумали']);

        $this->assertProblem(409);
    }

    public function testRejectIsForbiddenForRegularUser(): void
    {
        $id = $this->pendingActivityId();

        $this->requestAs($this->demoUser(), 'POST', "/api/admin/activities/$id/reject", ['comment' => 'Нет']);

        $this->assertProblem(403);
    }

    /**
     * @param list<array<string, mixed>> $activities
     *
     * @return array<string, mixed>
     */
    private function findByName(array $activities, string $name): array
    {
        foreach ($activities as $activity) {
            if ($name === $activity['name']) {
                return $activity;
            }
        }

        self::fail(\sprintf('Активность «%s» не найдена в ответе', $name));
    }

    private function pendingActivityId(): string
    {
        foreach ($this->activities()->findByStatus(ActivityStatus::Pending) as $activity) {
            if (AppFixtures::PENDING_ACTIVITY_NAME === $activity->getName()) {
                return $activity->getId()->toRfc4122();
            }
        }

        self::fail('Фикстурная активность на модерации не найдена');
    }

    private function createPendingActivity(string $name): string
    {
        $activity = new Activity(
            owner: $this->demoUser(),
            name: $name,
            category: Category::Creativity,
            description: 'Ещё одна студия рисования. Вторая.',
            ageMin: 7,
            ageMax: 12,
            visitType: VisitType::Recurring,
            priceType: PriceType::Free,
            priceAmount: 0,
        );
        $branch = new ActivityBranch('', 'ул. Светланская, 1');
        $branch->addSlot(new ScheduleSlot(DayOfWeek::Monday, '10:00', '11:00'));
        $activity->addBranch($branch);

        $em = $this->em();
        $em->persist($activity);
        $em->flush();

        return $activity->getId()->toRfc4122();
    }

    private function activities(): ActivityRepository
    {
        $this->em()->clear();

        return self::getContainer()->get(ActivityRepository::class);
    }

    private function clubs(): ClubRepository
    {
        $this->em()->clear();

        return self::getContainer()->get(ClubRepository::class);
    }
}
