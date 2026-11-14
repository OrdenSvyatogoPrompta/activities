<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Entity\Club;
use App\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * Фикстуры: 18 кружков из моков фронта и кружок `akvarel` из одобренной активности.
 */
final class ClubCatalogTest extends ApiTestCase
{
    /** Поля `Club` фронта, кроме `branches` */
    private const array CLUB_FIELDS = [
        'id', 'name', 'categories', 'emoji', 'color', 'shortDescription', 'fullDescription',
        'age', 'ageMin', 'ageMax', 'days', 'visitType', 'schedule', 'address', 'phone',
        'price', 'priceAmount', 'priceType', 'tags',
    ];

    private const array BRANCH_FIELDS = ['id', 'name', 'address', 'phone', 'schedule', 'days', 'slots'];

    private const string ORGANIZER_CLUB = 'akvarel';

    public function testListReturnsWholePublishedCatalogSortedByName(): void
    {
        $clubs = $this->getJson('/api/clubs');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertTrue(array_is_list($clubs));

        $expectedSlugs = [...array_keys(self::snapshot()), self::ORGANIZER_CLUB];
        $slugs = array_column($clubs, 'id');
        self::assertEqualsCanonicalizing($expectedSlugs, $slugs);
        self::assertCount(19, $clubs);

        $names = array_column($clubs, 'name');
        $sorted = $names;
        usort($sorted, static fn (string $a, string $b): int => strcmp(mb_strtolower($a), mb_strtolower($b)));
        self::assertSame($sorted, $names, 'Каталог отсортирован по name');
    }

    public function testEveryClubHasContractShape(): void
    {
        foreach ($this->getJson('/api/clubs') as $club) {
            self::assertSame([...self::CLUB_FIELDS, 'branches'], array_keys($club), $club['id']);
            self::assertNotEmpty($club['branches'], 'У каждого кружка минимум один филиал: '.$club['id']);

            $first = $club['branches'][0];
            self::assertSame($first['address'], $club['address'], $club['id']);
            self::assertSame($first['phone'], $club['phone'], $club['id']);
            self::assertSame($first['schedule'], $club['schedule'], $club['id']);

            $unionDays = [];
            foreach ($club['branches'] as $branch) {
                self::assertSame(self::BRANCH_FIELDS, array_keys($branch), $club['id']);
                self::assertTrue(Uuid::isValid($branch['id']), $club['id']);
                self::assertIsString($branch['phone']);

                $slotDays = [];
                foreach ($branch['slots'] as $slot) {
                    self::assertSame(['day', 'timeFrom', 'timeTo'], array_keys($slot));
                    self::assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slot['timeFrom']);
                    self::assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slot['timeTo']);
                    self::assertLessThan($slot['timeTo'], $slot['timeFrom']);
                    $slotDays[] = $slot['day'];
                }
                self::assertSame(self::sortDays($slotDays), $branch['days'], $club['id'].' / '.$branch['name']);
                array_push($unionDays, ...$branch['days']);
            }
            self::assertSame(self::sortDays($unionDays), $club['days'], 'days — объединение филиалов: '.$club['id']);
        }
    }

    /**
     * Снимок: ответы для 18 фикстурных кружков совпадают с моками фронта по полям club.model.ts.
     *
     * Осознанные отличия от моков:
     * - `schedule` верхнего уровня — группы через `\n` (в моках «, » и « / »);
     * - `days` у swimming-dyush включает «Сб» — объединение дней всех филиалов;
     * - у кружков без филиалов в моках API отдаёт один филиал «Основной»;
     * - id филиалов — UUID вместо slug'ов моков, у филиалов есть `slots`.
     */
    public function testListMatchesFrontendMockSnapshot(): void
    {
        $actual = [];
        foreach ($this->getJson('/api/clubs') as $club) {
            $actual[$club['id']] = $club;
        }

        foreach (self::snapshot() as $slug => $mock) {
            self::assertArrayHasKey($slug, $actual);
            $club = $actual[$slug];
            $expected = self::applyKnownDeviations($mock);

            foreach (self::CLUB_FIELDS as $field) {
                self::assertSame($expected[$field], $club[$field], \sprintf('%s.%s', $slug, $field));
            }

            if (isset($mock['branches'])) {
                self::assertCount(\count($mock['branches']), $club['branches'], $slug);
                foreach ($mock['branches'] as $i => $mockBranch) {
                    foreach (['name', 'address', 'phone', 'schedule', 'days'] as $field) {
                        self::assertSame($mockBranch[$field], $club['branches'][$i][$field], \sprintf('%s.branches[%d].%s', $slug, $i, $field));
                    }
                }
            } else {
                self::assertCount(1, $club['branches'], $slug);
                $branch = $club['branches'][0];
                self::assertSame('Основной', $branch['name'], $slug);
                self::assertSame($expected['address'], $branch['address'], $slug);
                self::assertSame($expected['phone'], $branch['phone'], $slug);
                self::assertSame($expected['schedule'], $branch['schedule'], $slug);
                self::assertSame($expected['days'], $branch['days'], $slug);
            }
        }
    }

    public function testSwimmingBranchesExposeSlots(): void
    {
        $club = $this->getJson('/api/clubs/swimming-dyush');

        self::assertResponseIsSuccessful();
        self::assertSame(['Борисенко', 'Вторая Речка', 'Снеговая'], array_column($club['branches'], 'name'));
        self::assertSame([
            ['day' => 'Пн', 'timeFrom' => '15:00', 'timeTo' => '18:00'],
            ['day' => 'Вт', 'timeFrom' => '10:00', 'timeTo' => '12:00'],
            ['day' => 'Ср', 'timeFrom' => '15:00', 'timeTo' => '18:00'],
            ['day' => 'Чт', 'timeFrom' => '10:00', 'timeTo' => '12:00'],
            ['day' => 'Пт', 'timeFrom' => '15:00', 'timeTo' => '18:00'],
        ], $club['branches'][0]['slots']);
        self::assertSame("Пн, Ср, Пт · 15:00–18:00\nВт, Чт · 10:00–12:00", $club['schedule']);
    }

    public function testScheduleNoteOverridesSlots(): void
    {
        $club = $this->getJson('/api/clubs/guitar');

        self::assertSame('По договорённости', $club['schedule']);
        self::assertSame(['Пн', 'Ср', 'Пт', 'Сб'], $club['days']);
        self::assertNotEmpty($club['branches'][0]['slots']);
    }

    public function testOrganizerClubFromApprovedActivity(): void
    {
        $club = $this->getJson('/api/clubs/'.self::ORGANIZER_CLUB);

        self::assertResponseIsSuccessful();
        self::assertSame('Акварель', $club['name']);
        self::assertSame(['Творчество'], $club['categories']);
        self::assertSame('#f59e0b', $club['color']);
        self::assertSame('5–14 лет', $club['age']);
        self::assertSame('2 500 ₽ / мес', $club['price']);
        self::assertSame('pushkin', $club['priceType']);
        self::assertSame(['Вт', 'Ср', 'Чт', 'Пт', 'Сб'], $club['days']);

        self::assertSame(['Основной', 'Снеговая'], array_column($club['branches'], 'name'));
        self::assertSame('Вт, Чт, Сб · 15:30–17:30', $club['schedule']);
        self::assertSame('пр. Океанский, 44', $club['address']);
        self::assertSame('+7 423 243-55-20', $club['phone']);

        $snegovaya = $club['branches'][1];
        self::assertSame('ул. Снеговая, 10', $snegovaya['address']);
        self::assertSame('', $snegovaya['phone'], 'Телефон филиала не задан — пустая строка, как требует модель фронта');
        self::assertSame('Ср, Пт · 16:00–18:00', $snegovaya['schedule']);
    }

    public function testShowReturnsSameClubAsList(): void
    {
        $list = [];
        foreach ($this->getJson('/api/clubs') as $club) {
            $list[$club['id']] = $club;
        }

        foreach (['swimming-dyush', 'guitar', self::ORGANIZER_CLUB] as $slug) {
            self::assertSame($list[$slug], $this->getJson('/api/clubs/'.$slug), $slug);
            self::assertResponseIsSuccessful();
        }
    }

    public function testUnknownSlugReturns404Problem(): void
    {
        $this->client->request('GET', '/api/clubs/no-such-club');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('Кружок не найден', $this->json()['detail']);
    }

    public function testUnpublishedClubIsHiddenFromListAndShow(): void
    {
        $em = $this->entityManager();
        $club = $em->getRepository(Club::class)->findOneBy(['slug' => 'technar']);
        self::assertNotNull($club);
        $club->setPublished(false);
        $em->flush();

        $slugs = array_column($this->getJson('/api/clubs'), 'id');
        self::assertResponseIsSuccessful();
        self::assertNotContains('technar', $slugs);
        self::assertCount(18, $slugs);

        $this->client->request('GET', '/api/clubs/technar');
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testCatalogIsPublic(): void
    {
        $this->client->request('GET', '/api/clubs');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/clubs/swimming-dyush');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cacheableUrls(): iterable
    {
        yield 'list' => ['/api/clubs'];
        yield 'show' => ['/api/clubs/swimming-dyush'];
    }

    #[DataProvider('cacheableUrls')]
    public function testResponsesAreCacheableWithEtag(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        // Symfony сортирует директивы: заголовок выглядит как «max-age=60, public»
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('60', (string) $response->headers->getCacheControlDirective('max-age'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        $etag = $response->getEtag();
        self::assertNotEmpty($etag);

        // Тот же каталог — тот же ETag
        $this->client->request('GET', $url);
        self::assertSame($etag, $this->client->getResponse()->getEtag());

        $this->client->request('GET', $url, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        self::assertSame($etag, $this->client->getResponse()->getEtag());

        $this->client->request('GET', $url, server: ['HTTP_IF_NONE_MATCH' => '"stale"']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testEtagChangesWhenCatalogChanges(): void
    {
        $this->client->request('GET', '/api/clubs');
        $etag = $this->client->getResponse()->getEtag();

        $em = $this->entityManager();
        $club = $em->getRepository(Club::class)->findOneBy(['slug' => 'nota']);
        self::assertNotNull($club);
        $club->setName('Нота+');
        $em->flush();

        $this->client->request('GET', '/api/clubs', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(200);
        self::assertNotSame($etag, $this->client->getResponse()->getEtag());
    }

    /**
     * Моки `CLUBS` фронта до перехода на API, ключ — slug.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function snapshot(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/clubs-snapshot.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $mock
     *
     * @return array<string, mixed>
     */
    private static function applyKnownDeviations(array $mock): array
    {
        // Группы расписания разделяются «\n»: в моках « / » или «, » после времени
        $mock['schedule'] = preg_replace('/ \/ |(?<=\d), /u', "\n", $mock['schedule']);

        if ('swimming-dyush' === $mock['id']) {
            // Филиал «Снеговая» занимается и в субботу
            $mock['days'] = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
        }

        return $mock;
    }

    /**
     * @param list<string> $days
     *
     * @return list<string>
     */
    private static function sortDays(array $days): array
    {
        return array_values(array_intersect(['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'], $days));
    }

    /**
     * @return array<mixed>
     */
    private function getJson(string $url): array
    {
        $this->client->request('GET', $url);

        return $this->json();
    }
}
