<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CategoryInput;
use App\Dto\CategoryOutput;
use App\Service\CategoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class CategoryController
{
    public function __construct(
        private readonly CategoryService $service,
    ) {
    }

    #[Route('/api/categories', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $enabledOnly = 'true' === $request->query->get('enabled_only');

        return $this->json($this->service->getTree($enabledOnly));
    }

    #[Route('/api/categories/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->service->getById($id));
    }

    #[Route('/api/categories', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $input = new CategoryInput(
            name: $data['name'] ?? null,
            parentId: $data['parent_id'] ?? null,
            sortOrder: $data['sort_order'] ?? 0,
            icon: $data['icon'] ?? null,
            seoTitle: $data['seo_title'] ?? null,
            seoDescription: $data['seo_description'] ?? null,
            seoKeywords: $data['seo_keywords'] ?? null,
            isEnabled: $data['is_enabled'] ?? null,
        );

        $category = $this->service->create($input);

        return $this->json(
            CategoryOutput::fromEntity($category)->toArray(),
            Response::HTTP_CREATED,
            ['Location' => '/api/categories/' . $category->getId()]
        );
    }

    #[Route('/api/categories/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $input = new CategoryInput(
            name: $data['name'] ?? null,
            parentId: $data['parent_id'] ?? null,
            sortOrder: $data['sort_order'] ?? 0,
            icon: $data['icon'] ?? null,
            seoTitle: $data['seo_title'] ?? null,
            seoDescription: $data['seo_description'] ?? null,
            seoKeywords: $data['seo_keywords'] ?? null,
            isEnabled: $data['is_enabled'] ?? null,
        );

        $category = $this->service->update($id, $input);

        return $this->json(CategoryOutput::fromEntity($category)->toArray());
    }

    #[Route('/api/categories/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/categories/{id}/toggle', methods: ['PATCH'])]
    public function toggle(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $isEnabled = (bool) ($data['is_enabled'] ?? false);

        return $this->json(CategoryOutput::fromEntity($this->service->toggle($id, $isEnabled))->toArray());
    }

    #[Route('/api/categories/{id}/move', methods: ['PATCH'])]
    public function move(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $newParentId = $data['parent_id'] ?? null;
        $sortOrder = (int) ($data['sort_order'] ?? 0);

        return $this->json(CategoryOutput::fromEntity($this->service->move($id, $newParentId, $sortOrder))->toArray());
    }

    /**
     * @param array<string, string> $headers
     */
    private function json(mixed $data, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}
