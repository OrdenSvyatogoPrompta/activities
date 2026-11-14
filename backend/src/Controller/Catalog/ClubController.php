<?php

declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Controller\ApiController;
use App\Repository\ClubRepository;
use App\Service\Catalog\ClubViewFactory;
use App\Service\Catalog\PublicCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Весь опубликованный каталог одним списком: фильтрует клиент.
 */
#[Route('/api/clubs', name: 'api_clubs_')]
final class ClubController extends ApiController
{
    public function __construct(
        private readonly ClubRepository $clubs,
        private readonly ClubViewFactory $views,
        private readonly PublicCache $cache,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $response = $this->json($this->views->createList($this->clubs->findPublished()));

        return $this->cache->apply($response, $request);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $club = $this->clubs->findOnePublishedBySlug($slug)
            ?? throw $this->createNotFoundException('Кружок не найден');

        $response = $this->json($this->views->create($club));

        return $this->cache->apply($response, $request);
    }
}
