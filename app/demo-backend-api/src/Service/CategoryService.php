<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CategoryInput;
use App\Dto\CategoryOutput;
use App\Entity\Category;
use App\Exception\CategoryHasChildrenException;
use App\Exception\CategoryNotFoundException;
use App\Exception\CircularReferenceException;
use App\Repository\CategoryRepositoryInterface;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTree(bool $enabledOnly = false): array
    {
        $categories = $this->repository->findAllOrdered();

        $childrenMap = [];
        foreach ($categories as $category) {
            $parentId = $category->getParent()?->getId() ?? 0;
            $childrenMap[$parentId][] = $category;
        }

        return $this->buildTreeFromMap(0, $childrenMap, $enabledOnly);
    }

    /**
     * @return array<string, mixed>
     */
    public function getById(int $id): array
    {
        $category = $this->findCategory($id);

        return CategoryOutput::fromEntityShallow($category)->toArray();
    }

    public function create(CategoryInput $input): Category
    {
        if (null === $input->name || '' === trim($input->name)) {
            throw new \InvalidArgumentException('Category name is required');
        }

        $category = new Category();
        $category->setName($input->name);

        if (null !== $input->parentId) {
            $parent = $this->findCategory($input->parentId);
            $category->setParent($parent);
        }

        $category->setSortOrder($input->sortOrder);

        if (null !== $input->icon) {
            $category->setIcon($input->icon);
        }
        if (null !== $input->seoTitle) {
            $category->setSeoTitle($input->seoTitle);
        }
        if (null !== $input->seoDescription) {
            $category->setSeoDescription($input->seoDescription);
        }
        if (null !== $input->seoKeywords) {
            $category->setSeoKeywords($input->seoKeywords);
        }
        if (null !== $input->isEnabled) {
            $category->setIsEnabled($input->isEnabled);
        }

        $this->repository->save($category);

        return $category;
    }

    public function update(int $id, CategoryInput $input): Category
    {
        $category = $this->findCategory($id);

        if (null !== $input->parentId) {
            $this->validateNoCircularReference($id, $input->parentId);
            $parent = $this->findCategory($input->parentId);
            $category->setParent($parent);
        }

        if (null !== $input->name) {
            $category->setName($input->name);
        }
        if ($input->sortOrder !== $category->getSortOrder()) {
            $category->setSortOrder($input->sortOrder);
        }
        if (null !== $input->icon) {
            $category->setIcon($input->icon);
        }
        if (null !== $input->seoTitle) {
            $category->setSeoTitle($input->seoTitle);
        }
        if (null !== $input->seoDescription) {
            $category->setSeoDescription($input->seoDescription);
        }
        if (null !== $input->seoKeywords) {
            $category->setSeoKeywords($input->seoKeywords);
        }
        if (null !== $input->isEnabled) {
            $category->setIsEnabled($input->isEnabled);
        }

        $this->repository->save($category);

        return $category;
    }

    public function delete(int $id): void
    {
        $category = $this->findCategory($id);

        if (!$category->getChildren()->isEmpty()) {
            throw new CategoryHasChildrenException();
        }

        $this->repository->remove($category);
    }

    public function toggle(int $id, bool $isEnabled): Category
    {
        $category = $this->findCategory($id);
        $category->setIsEnabled($isEnabled);
        $this->repository->save($category);

        return $category;
    }

    public function move(int $id, ?int $newParentId, int $sortOrder): Category
    {
        $category = $this->findCategory($id);

        if (null !== $newParentId) {
            $this->validateNoCircularReference($id, $newParentId);
            $parent = $this->findCategory($newParentId);
            $category->setParent($parent);
        } else {
            $category->setParent(null);
        }

        $category->setSortOrder($sortOrder);
        $this->repository->save($category);

        return $category;
    }

    private function findCategory(int $id): Category
    {
        $category = $this->repository->findById($id);
        if (null === $category) {
            throw new CategoryNotFoundException();
        }

        return $category;
    }

    private function validateNoCircularReference(int $categoryId, int $newParentId): void
    {
        if ($categoryId === $newParentId) {
            throw new CircularReferenceException();
        }

        $parentCandidates = $this->getAncestorIds($newParentId);
        if (in_array($categoryId, $parentCandidates, true)) {
            throw new CircularReferenceException();
        }
    }

    /**
     * @return int[]
     */
    private function getAncestorIds(int $categoryId): array
    {
        return $this->repository->findAncestorIds($categoryId);
    }

    /**
     * @param array<int, Category[]> $childrenMap
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTreeFromMap(int $parentId, array $childrenMap, bool $enabledOnly): array
    {
        $tree = [];
        $children = $childrenMap[$parentId] ?? [];

        foreach ($children as $category) {
            if ($enabledOnly && !$category->isEnabled()) {
                continue;
            }

            $output = CategoryOutput::fromEntityWithoutChildren($category)->toArray();
            $output['children'] = $this->buildTreeFromMap(
                $category->getId(),
                $childrenMap,
                $enabledOnly
            );

            $tree[] = $output;
        }

        return $tree;
    }
}
