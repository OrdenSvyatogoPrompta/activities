<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Dto\Admin\EnrollmentListQuery;
use App\Repository\EnrollmentRepository;
use App\Service\Admin\AdminViewFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class EnrollmentListController extends ApiController
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
