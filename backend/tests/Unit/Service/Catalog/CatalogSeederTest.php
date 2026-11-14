<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Catalog;

use App\Entity\Club;
use App\Repository\ClubRepository;
use App\Service\Catalog\CatalogData;
use App\Service\Catalog\CatalogSeeder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CatalogSeederTest extends TestCase
{
    public function testSeedsOnlyClubsMissingBySlug(): void
    {
        $existing = 'swimming-dyush';
        $clubs = $this->createStub(ClubRepository::class);
        $clubs->method('slugExists')->willReturnCallback(static fn (string $slug): bool => $slug === $existing);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $club) use (&$persisted): void {
            $persisted[] = $club;
        });
        $entityManager->expects(self::never())->method('flush');

        $created = (new CatalogSeeder($clubs, $entityManager))->seed();

        self::assertCount(count(CatalogData::clubs()) - 1, $created);
        self::assertSame($created, $persisted);
        $slugs = array_map(static fn (Club $club): string => $club->getSlug(), $created);
        self::assertNotContains($existing, $slugs);
        foreach ($created as $club) {
            self::assertNotEmpty($club->getBranches());
            self::assertTrue($club->isPublished());
            self::assertNull($club->getOwner());
        }
    }
}
