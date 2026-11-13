<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /api/dictionaries (группа D, низкий приоритет).
 */
final class DictionariesTest extends WebTestCase
{
    public function testReturnsEnumValuesAsInFrontendModel(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/dictionaries');

        if (401 === $client->getResponse()->getStatusCode()) {
            self::markTestIncomplete('Нужно правило `^/api/dictionaries$` с PUBLIC_ACCESS в security.yaml');
        }

        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('public'));
        self::assertNotEmpty($client->getResponse()->getEtag());
        self::assertSame([
            'categories' => ['Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'],
            'days' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            'priceTypes' => ['free', 'pushkin', 'paid'],
            'visitTypes' => ['recurring', 'oneTime', 'both'],
        ], json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }
}
