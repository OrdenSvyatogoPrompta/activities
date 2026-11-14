<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Tests\Functional\ApiTestCase;

final class DictionariesTest extends ApiTestCase
{
    public function testReturnsEnumValuesAsInFrontendModel(): void
    {
        $this->client->request('GET', '/api/dictionaries');

        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'));
        self::assertNotEmpty($this->client->getResponse()->getEtag());
        self::assertSame([
            'categories' => ['Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'],
            'days' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            'priceTypes' => ['free', 'pushkin', 'paid'],
            'visitTypes' => ['recurring', 'oneTime', 'both'],
        ], $this->json());
    }
}
