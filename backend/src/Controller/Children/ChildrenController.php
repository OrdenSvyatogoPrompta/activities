<?php

declare(strict_types=1);

namespace App\Controller\Children;

use App\Dto\Children\ChildView;
use App\Dto\Children\CreateChildRequest;
use App\Dto\Children\UpdateChildRequest;
use App\Entity\User;
use App\Service\Children\ChildService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Дети текущего пользователя. Доступ — любой аутентифицированный (access_control `^/api`).
 */
#[Route('/api/me/children', name: 'api_me_children_')]
final class ChildrenController extends AbstractController
{
    public function __construct(
        private readonly ChildService $children,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map(ChildView::fromEntity(...), $this->children->list($user)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[CurrentUser] User $user, #[MapRequestPayload] CreateChildRequest $request): JsonResponse
    {
        $child = $this->children->create($user, $request);

        return $this->json(ChildView::fromEntity($child), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] UpdateChildRequest $payload,
        Request $request,
    ): JsonResponse {
        // null в DTO не отличает «поле не передано» от «стереть»; для фамилии это важно
        $lastNameProvided = $request->getPayload()->has('lastName');
        $child = $this->children->update($user, $id, $payload, $lastNameProvided);

        return $this->json(ChildView::fromEntity($child));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $user, string $id): Response
    {
        $this->children->delete($user, $id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
