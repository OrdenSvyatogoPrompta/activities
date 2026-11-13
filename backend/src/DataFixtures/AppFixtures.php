<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\ActivityBranch;
use App\Entity\Branch;
use App\Entity\Child;
use App\Entity\Club;
use App\Entity\Enrollment;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\EnrollmentStatus;
use App\Entity\Enum\Gender;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\ScheduleSlot;
use App\Entity\User;
use App\Service\SlugGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Данные моков фронтенда для dev/test (решение D7):
 * - 18 сидовых кружков каталога без владельца (CatalogData);
 * - демо-пользователь с детьми и заявками (MOCK_USER из auth.service.ts);
 * - одобренная активность «Акварель» демо-пользователя со своим кружком и 4 входящими заявками
 *   от отдельных пользователей; активность на модерации;
 * - администратор.
 *
 * Пароль всех пользователей, кроме админа, — DEMO_PASSWORD.
 */
final class AppFixtures extends Fixture
{
    public const string DEMO_EMAIL = 'maria@example.com';
    public const string DEMO_PASSWORD = 'password';
    public const string ADMIN_EMAIL = 'admin@example.com';
    public const string ADMIN_PASSWORD = 'admin';

    /** Название активности на модерации у демо-пользователя. */
    public const string PENDING_ACTIVITY_NAME = 'Настольные игры';

    /** Ссылки (getReference) для тестов других групп. */
    public const string REF_DEMO_USER = 'user-demo';
    public const string REF_ADMIN = 'user-admin';
    public const string REF_ORGANIZER_CLUB = 'club-organizer';
    public const string REF_APPROVED_ACTIVITY = 'activity-approved';
    public const string REF_PENDING_ACTIVITY = 'activity-pending';

    /** @var array<string, Club> */
    private array $clubs = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SlugGenerator $slugGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadCatalog($manager);

        $admin = $this->user($manager, self::ADMIN_EMAIL, 'Администратор', self::ADMIN_PASSWORD)->promoteToAdmin();
        $this->addReference(self::REF_ADMIN, $admin);

        $maria = $this->user($manager, self::DEMO_EMAIL, 'Иванова Мария');
        $this->addReference(self::REF_DEMO_USER, $maria);
        $this->loadDemoFamily($manager, $maria);
        $this->loadOrganizerActivities($manager, $maria, $admin);

        $manager->flush();
    }

    private function loadCatalog(ObjectManager $manager): void
    {
        foreach (CatalogData::clubs() as $data) {
            $club = new Club(
                slug: $data['slug'],
                name: $data['name'],
                categories: array_map(static fn (string $category): Category => Category::from($category), $data['categories']),
                emoji: $data['emoji'],
                color: $data['color'],
                shortDescription: $data['shortDescription'],
                fullDescription: $data['fullDescription'],
                ageMin: $data['ageMin'],
                ageMax: $data['ageMax'],
                visitType: VisitType::from($data['visitType']),
                priceType: PriceType::from($data['priceType']),
                priceAmount: $data['priceAmount'],
                tags: $data['tags'],
            );

            foreach ($data['branches'] as $branchData) {
                $branch = new Branch($branchData['name'], $branchData['address'], $branchData['phone'], $branchData['scheduleNote'] ?? null);
                foreach ($branchData['slots'] as [$days, $from, $to]) {
                    foreach (self::days($days) as $day) {
                        $branch->addSlot(new ScheduleSlot($day, $from, $to));
                    }
                }
                $club->addBranch($branch);
            }

            $manager->persist($club);
            $this->clubs[$club->getSlug()] = $club;
        }
    }

    /**
     * Дети и заявки демо-пользователя (MOCK_USER.children / enrollments).
     */
    private function loadDemoFamily(ObjectManager $manager, User $maria): void
    {
        $artem = new Child($maria, 'Артём', 'Иванов', 8, Gender::Male);
        $sonya = new Child($maria, 'Соня', 'Иванова', 5, Gender::Female);
        $manager->persist($artem);
        $manager->persist($sonya);

        $swimming = $this->clubs['swimming-dyush'];
        $borisenko = null;
        foreach ($swimming->getBranches() as $branch) {
            if ('Борисенко' === $branch->getName()) {
                $borisenko = $branch;
            }
        }

        $artemSwimming = new Enrollment($maria, $swimming, $borisenko, $artem, new \DateTimeImmutable('2026-10-15 10:00'));
        // Фикстуры работают без аутентифицированного пользователя, поэтому статус ставится
        // напрямую, а не через workflow (его guard требует владельца кружка или админа)
        $artemSwimming->setMarking(EnrollmentStatus::Confirmed->value);
        $manager->persist($artemSwimming);

        $gymnastics = $this->clubs['gimnastika-voskhod'];
        $manager->persist(new Enrollment($maria, $gymnastics, $gymnastics->getSingleBranch(), $sonya, new \DateTimeImmutable('2026-11-01 10:00')));
    }

    /**
     * Активности демо-пользователя как организатора (MOCK_USER.activities) и активность на модерации.
     */
    private function loadOrganizerActivities(ObjectManager $manager, User $maria, User $admin): void
    {
        $description = 'Художественная студия для детей. Занятия акварелью, гуашью, пастелью. Небольшие группы — индивидуальный подход.';
        $activity = new Activity(
            owner: $maria,
            name: 'Акварель',
            category: Category::Creativity,
            description: $description,
            ageMin: 5,
            ageMax: 14,
            visitType: VisitType::Both,
            priceType: PriceType::Pushkin,
            priceAmount: 2500,
            tags: ['рисование'],
            emoji: '🎨',
        );
        $main = new ActivityBranch('Основной', 'пр. Океанский, 44', '+7 423 243-55-20');
        self::addSlots($main, 'Вт, Чт, Сб', '15:30', '17:30');
        $snegovaya = new ActivityBranch('Снеговая', 'ул. Снеговая, 10');
        self::addSlots($snegovaya, 'Ср, Пт', '16:00', '18:00');
        $activity->addBranch($main)->addBranch($snegovaya);
        $manager->persist($activity);

        // Кружок из одобренной активности — так, как его создаёт модерация (группа H)
        $club = new Club(
            slug: $this->slugGenerator->generate($activity->getName(), fn (string $slug): bool => isset($this->clubs[$slug])),
            name: $activity->getName(),
            categories: [$activity->getCategory()],
            emoji: $activity->getEmoji(),
            color: $activity->getCategory()->color(),
            shortDescription: 'Художественная студия для детей.',
            fullDescription: $description,
            ageMin: $activity->getAgeMin(),
            ageMax: $activity->getAgeMax(),
            visitType: $activity->getVisitType(),
            priceType: $activity->getPriceType(),
            priceAmount: $activity->getPriceAmount(),
            tags: $activity->getTags(),
            owner: $maria,
            sourceActivity: $activity,
        );
        $clubBranches = [];
        foreach ($activity->getBranches() as $activityBranch) {
            $branch = Branch::fromActivityBranch($activityBranch);
            $club->addBranch($branch);
            $clubBranches[$activityBranch->getName()] = $branch;
        }
        $activity->approve($club, $admin);
        $manager->persist($club);
        $this->clubs[$club->getSlug()] = $club;
        $this->addReference(self::REF_ORGANIZER_CLUB, $club);
        $this->addReference(self::REF_APPROVED_ACTIVITY, $activity);

        // Входящие заявки (MOCK_USER.activities[0].enrollments) — от отдельных пользователей
        $incoming = [
            ['anna.petrova@example.com', 'Петрова Анна', ['Маша', 'Петрова', 7, Gender::Female], 'Основной', '2026-10-20'],
            ['igor.sidorov@example.com', 'Сидоров Игорь', null, 'Основной', '2026-10-22'],
            ['vera.kozlova@example.com', 'Козлова Вера', ['Дима', 'Козлов', 9, Gender::Male], 'Снеговая', '2026-11-01'],
            ['elena.novikova@example.com', 'Новикова Елена', ['Катя', 'Новикова', 6, Gender::Female], 'Снеговая', '2026-11-05'],
        ];
        foreach ($incoming as [$email, $name, $childData, $branchName, $date]) {
            $user = $this->user($manager, $email, $name);
            $child = null;
            if (null !== $childData) {
                $child = new Child($user, ...$childData);
                $manager->persist($child);
            }
            $manager->persist(new Enrollment($user, $club, $clubBranches[$branchName], $child, new \DateTimeImmutable($date.' 10:00')));
        }

        // Активность на модерации — для проверки одобрения/отклонения
        $pending = new Activity(
            owner: $maria,
            name: self::PENDING_ACTIVITY_NAME,
            category: Category::Science,
            description: 'Клуб настольных игр для детей и родителей. Стратегии, головоломки и командные игры развивают логику и умение договариваться.',
            ageMin: 6,
            ageMax: 16,
            visitType: VisitType::Both,
            priceType: PriceType::Paid,
            priceAmount: 1200,
            tags: ['настольные игры', 'логика'],
            emoji: '🎲',
        );
        $pendingBranch = new ActivityBranch('Основной', 'ул. Фокина, 12', '+7 924 111-22-33');
        self::addSlots($pendingBranch, 'Сб', '12:00', '14:00');
        $pending->addBranch($pendingBranch);
        $manager->persist($pending);
        $this->addReference(self::REF_PENDING_ACTIVITY, $pending);
    }

    private function user(ObjectManager $manager, string $email, string $name, string $password = self::DEMO_PASSWORD): User
    {
        $user = new User($email, $name);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $manager->persist($user);

        return $user;
    }

    private static function addSlots(ActivityBranch $branch, string $days, string $from, string $to): void
    {
        foreach (self::days($days) as $day) {
            $branch->addSlot(new ScheduleSlot($day, $from, $to));
        }
    }

    /**
     * «Пн, Ср, Пт» → [DayOfWeek::Monday, …].
     *
     * @return list<DayOfWeek>
     */
    private static function days(string $days): array
    {
        return array_map(static fn (string $day): DayOfWeek => DayOfWeek::from(trim($day)), explode(',', $days));
    }
}
