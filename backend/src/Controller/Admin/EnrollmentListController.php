<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dto\Admin\EnrollmentListQuery;
use App\Repository\EnrollmentRepository;
use App\Service\Admin\AdminViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Заявки на запись для администратора (группа H): `?status=pending&ownerless=true` — очередь
 * заявок в кружки без владельца. Подтверждение/отклонение — эндпоинты группы G
 * (`/api/enrollments/{id}/confirm|reject`).
 */
#[IsGranted('ROLE_ADMIN')]
final class EnrollmentListController extends AbstractController
{
    #[Route('/api/admin/enrollments', name: 'api_admin_enrollments_list', methods: ['GET'])]
    public function __invoke(
        EnrollmentRepository $enrollmentRepository,
        AdminViewFactory $views,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        EnrollmentListQuery $query = new EnrollmentListQuery(),
    ): JsonResponse {
        return $this->json($views->enrollments(
            $enrollmentRepository->findForAdmin($query->statusEnum(), $query->ownerless),
        ));
    }
}
