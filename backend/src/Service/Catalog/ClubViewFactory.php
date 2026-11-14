<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Dto\Catalog\BranchView;
use App\Dto\Catalog\ClubView;
use App\Dto\Catalog\ScheduleSlotView;
use App\Entity\Branch;
use App\Entity\Club;
use App\Service\Formatter\AgeFormatter;
use App\Service\Formatter\PriceFormatter;
use App\Service\Formatter\ScheduleFormatter;

final readonly class ClubViewFactory
{
    public function __construct(
        private AgeFormatter $ageFormatter,
        private PriceFormatter $priceFormatter,
        private ScheduleFormatter $scheduleFormatter,
    ) {
    }

    /**
     * @param iterable<Club> $clubs
     *
     * @return list<ClubView>
     */
    public function createList(iterable $clubs): array
    {
        $views = [];
        foreach ($clubs as $club) {
            $views[] = $this->create($club);
        }

        return $views;
    }

    public function create(Club $club): ClubView
    {
        $branches = array_map($this->createBranch(...), $this->sortedBranches($club));
        $first = $branches[0] ?? null;

        return new ClubView(
            id: $club->getSlug(),
            name: $club->getName(),
            categories: array_column($club->getCategories(), 'value'),
            emoji: $club->getEmoji(),
            color: $club->getColor(),
            shortDescription: $club->getShortDescription(),
            fullDescription: $club->getFullDescription(),
            age: $this->ageFormatter->format($club->getAgeMin(), $club->getAgeMax()),
            ageMin: $club->getAgeMin(),
            ageMax: $club->getAgeMax(),
            days: array_column($club->getDays(), 'value'),
            visitType: $club->getVisitType()->value,
            schedule: $first?->schedule ?? '',
            address: $first?->address ?? '',
            phone: $first?->phone ?? '',
            price: $this->priceFormatter->format($club->getPriceType(), $club->getPriceAmount()),
            priceAmount: $club->getPriceAmount(),
            priceType: $club->getPriceType()->value,
            tags: array_values($club->getTags()),
            branches: $branches,
        );
    }

    private function createBranch(Branch $branch): BranchView
    {
        return new BranchView(
            id: $branch->getId()->toRfc4122(),
            name: $branch->getName(),
            address: $branch->getAddress(),
            // Во фронтовой модели phone — обязательная строка
            phone: $branch->getPhone() ?? '',
            schedule: $this->scheduleFormatter->formatBranch($branch),
            days: array_column($branch->getDays(), 'value'),
            slots: array_map(ScheduleSlotView::fromEntity(...), $branch->getSlots()),
        );
    }

    /**
     * @return list<Branch>
     */
    private function sortedBranches(Club $club): array
    {
        $branches = $club->getBranches()->toArray();
        usort($branches, static fn (Branch $a, Branch $b): int => $a->getPosition() <=> $b->getPosition());

        return $branches;
    }
}
