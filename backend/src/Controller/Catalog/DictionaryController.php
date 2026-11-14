<?php

declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Controller\ApiController;
use App\Dto\Catalog\DictionariesView;
use App\Entity\Enum\Category;
use App\Entity\Enum\DayOfWeek;
use App\Entity\Enum\PriceType;
use App\Entity\Enum\VisitType;
use App\Service\Catalog\PublicCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DictionaryController extends ApiController
{
    #[Route('/api/dictionaries', name: 'api_dictionaries', methods: ['GET'])]
    public function __invoke(Request $request, PublicCache $cache): Response
    {
        $response = $this->json(new DictionariesView(
            categories: Category::values(),
            days: DayOfWeek::values(),
            priceTypes: PriceType::values(),
            visitTypes: VisitType::values(),
        ));

        return $cache->apply($response, $request);
    }
}
