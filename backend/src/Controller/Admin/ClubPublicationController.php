<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\Admin\ClubPublicationPayload;
use App\Dto\Admin\ClubPublicationView;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Снятие кружка с публикации и возврат в каталог (группа H, опционально).
 */
#[IsGranted('ROLE_ADMIN')]
final class ClubPublicationController extends AbstractController
{
    #[Route('/api/admin/clubs/{slug}', name: 'api_admin_clubs_publication', methods: ['PATCH'])]
    public function __invoke(
        string $slug,
        #[MapRequestPayload] ClubPublicationPayload $payload,
        ClubRepository $clubRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $club = $clubRepository->findOneBySlugWithBranches($slug)
            ?? throw new NotFoundHttpException('Кружок не найден');

        $club->setPublished($payload->isPublished);
        $entityManager->flush();

        return $this->json(ClubPublicationView::fromClub($club));
    }
}
