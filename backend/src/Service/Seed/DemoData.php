<?php

declare(strict_types=1);

namespace App\Service\Seed;

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
use App\Service\Catalog\CatalogSeeder;
use App\Service\SlugGenerator;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Каталог, демо-пользователь с детьми, заявками и активностями, администратор.
 * Общий набор для фикстур dev/test и первого деплоя на прод.
 */
final class DemoData
{
    public const string DEMO_EMAIL = 'maria@example.com';
    public const string DEMO_PASSWORD = 'password';
    public const string ADMIN_EMAIL = 'admin@example.com';
    public const string ADMIN_PASSWORD = 'admin';

    public const string PENDING_ACTIVITY_NAME = 'Настольные игры';

    /** @var array<string, Club> */
    private array $clubs = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SlugGenerator $slugGenerator,
        private readonly CatalogSeeder $catalogSeeder,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->clubs = [];
        $this->loadCatalog();

        $admin = $this->user($manager, self::ADMIN_EMAIL, 'Администратор', self::ADMIN_PASSWORD)->promoteToAdmin();

        $maria = $this->user($manager, self::DEMO_EMAIL, 'Иванова Мария');
        $this->loadDemoFamily($manager, $maria);
        $this->loadOrganizerActivities($manager, $maria, $admin);

        $manager->flush();
    }

    private function loadCatalog(): void
    {
        foreach ($this->catalogSeeder->seed() as $club) {
            $this->clubs[$club->getSlug()] = $club;
        }
    }

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
        // Мимо workflow: его guard без аутентифицированного пользователя блокирует переходы
        $artemSwimming->setMarking(EnrollmentStatus::Confirmed->value);
        $manager->persist($artemSwimming);

        $gymnastics = $this->clubs['gimnastika-voskhod'];
        $manager->persist(new Enrollment($maria, $gymnastics, $gymnastics->getSingleBranch(), $sonya, new \DateTimeImmutable('2026-11-01 10:00')));
    }

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

        // Так же, как кружок создаёт ActivityModerator::approve()
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
        foreach (DayOfWeek::parseList($days) as $day) {
            $branch->addSlot(new ScheduleSlot($day, $from, $to));
        }
    }
}
