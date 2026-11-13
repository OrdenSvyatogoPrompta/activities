<?php

declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Dto\Catalog\DictionariesView;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Service\Catalog\PublicCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Справочники перечислений каталога. Для публичного доступа нужно правило
 * `^/api/dictionaries$` с PUBLIC_ACCESS в security.yaml (без него — только с токеном).
 */
final class DictionaryController extends AbstractController
{
    #[Route('/api/dictionaries', name: 'api_dictionaries', methods: ['GET'])]
    public function __invoke(Request $request, PublicCache $cache): Response
    {
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);

        $response = $this->json(
            new DictionariesView(
                categories: $values(Category::cases()),
                days: $values(DayOfWeek::cases()),
                priceTypes: $values(PriceType::cases()),
                visitTypes: $values(VisitType::cases()),
            ),
            context: ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE],
        );

        return $cache->apply($response, $request);
    }
}
