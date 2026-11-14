<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Dto\Admin\ClubPublicationPayload;
use App\Dto\Admin\ClubPublicationView;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ClubPublicationController extends ApiController
{
    #[Route('/api/admin/clubs/{slug}', name: 'api_admin_clubs_publication', methods: ['PATCH'])]
    public function __invoke(
        string $slug,
        #[MapRequestPayload] ClubPublicationPayload $payload,
        ClubRepository $clubRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $club = $clubRepository->findOneBySlugWithBranches($slug)
            ?? throw $this->createNotFoundException('Кружок не найден');

        $club->setPublished($payload->isPublished);
        $entityManager->flush();

        return $this->json(ClubPublicationView::fromClub($club));
    }
}
