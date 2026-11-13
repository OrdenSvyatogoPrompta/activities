<?php

declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Repository\ClubRepository;
use App\Service\Catalog\ClubViewFactory;
use App\Service\Catalog\PublicCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичный каталог кружков (решение D4: весь опубликованный каталог одним списком,
 * фильтрация на клиенте). Доступ без токена — правило `^/api/clubs` в security.yaml.
 */
#[Route('/api/clubs', name: 'api_clubs_')]
final class ClubController extends AbstractController
{
    /** Русский текст без экранирования \uXXXX — ответ каталога заметно короче */
    private const array JSON_CONTEXT = ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE];

    public function __construct(
        private readonly ClubRepository $clubs,
        private readonly ClubViewFactory $views,
        private readonly PublicCache $cache,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $response = $this->json($this->views->createList($this->clubs->findPublished()), context: self::JSON_CONTEXT);

        return $this->cache->apply($response, $request);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $club = $this->clubs->findOnePublishedBySlug($slug)
            ?? throw $this->createNotFoundException('Кружок не найден');

        $response = $this->json($this->views->create($club), context: self::JSON_CONTEXT);

        return $this->cache->apply($response, $request);
    }
}
