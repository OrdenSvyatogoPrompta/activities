<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Branch;
use App\Entity\Club;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Entity\ScheduleSlot;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Добавляет кружки каталога, которых ещё нет: повторный запуск не дублирует
 * и не возвращает снятые с публикации. Flush — за вызывающим.
 */
final readonly class CatalogSeeder
{
    public function __construct(
        private ClubRepository $clubs,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Club> добавленные кружки
     */
    public function seed(): array
    {
        $created = [];
        foreach (CatalogData::clubs() as $data) {
            if ($this->clubs->slugExists($data['slug'])) {
                continue;
            }

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
                    foreach (DayOfWeek::parseList($days) as $day) {
                        $branch->addSlot(new ScheduleSlot($day, $from, $to));
                    }
                }
                $club->addBranch($branch);
            }

            $this->entityManager->persist($club);
            $created[] = $club;
        }

        return $created;
    }
}
