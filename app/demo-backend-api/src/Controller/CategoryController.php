<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CategoryInput;
use App\Dto\CategoryOutput;
use App\Service\CategoryService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Categories')]
class CategoryController
{
    public function __construct(
        private readonly CategoryService $service,
    ) {
    }

    #[Route('/api/categories', methods: ['GET'])]
    #[OA\Get(
        path: '/api/categories',
        summary: 'Get category tree',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'enabled_only', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category tree'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $enabledOnly = 'true' === $request->query->get('enabled_only');

        return $this->json($this->service->getTree($enabledOnly));
    }

    #[Route('/api/categories/{id}', methods: ['GET'])]
    #[OA\Get(
        path: '/api/categories/{id}',
        summary: 'Get category detail',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category detail with direct children'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->service->getById($id));
    }

    #[Route('/api/categories', methods: ['POST'])]
    #[OA\Post(
        path: '/api/categories',
        summary: 'Create a category',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Tops'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                    new OA\Property(property: 'icon', type: 'string', nullable: true),
                    new OA\Property(property: 'seo_title', type: 'string', nullable: true),
                    new OA\Property(property: 'seo_description', type: 'string', nullable: true),
                    new OA\Property(property: 'seo_keywords', type: 'string', nullable: true),
                    new OA\Property(property: 'is_enabled', type: 'boolean'),
                ],
                type: 'object'
            )
        ),
        tags: ['Categories'],
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 400, description: 'Validation error'),
        ]
    )]
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
    #[OA\Put(
        path: '/api/categories/{id}',
        summary: 'Update a category',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                    new OA\Property(property: 'icon', type: 'string', nullable: true),
                    new OA\Property(property: 'seo_title', type: 'string', nullable: true),
                    new OA\Property(property: 'seo_description', type: 'string', nullable: true),
                    new OA\Property(property: 'seo_keywords', type: 'string', nullable: true),
                    new OA\Property(property: 'is_enabled', type: 'boolean'),
                ],
                type: 'object'
            )
        ),
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category updated'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 422, description: 'Circular reference detected'),
        ]
    )]
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
    #[OA\Delete(
        path: '/api/categories/{id}',
        summary: 'Delete a category',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Category deleted'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 409, description: 'Category has children'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/categories/{id}/toggle', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/categories/{id}/toggle',
        summary: 'Toggle category enabled status',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                required: ['is_enabled'],
                properties: [
                    new OA\Property(property: 'is_enabled', type: 'boolean'),
                ],
                type: 'object'
            )
        ),
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Toggle result'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function toggle(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $isEnabled = (bool) ($data['is_enabled'] ?? false);

        return $this->json(CategoryOutput::fromEntity($this->service->toggle($id, $isEnabled))->toArray());
    }

    #[Route('/api/categories/{id}/move', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/categories/{id}/move',
        summary: 'Move a category in the tree',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'sort_order', type: 'integer'),
                ],
                type: 'object'
            )
        ),
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category moved'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 422, description: 'Circular reference detected'),
        ]
    )]
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
