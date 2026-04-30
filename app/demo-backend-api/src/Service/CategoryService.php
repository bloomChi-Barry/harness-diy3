<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CategoryInput;
use App\Dto\CategoryOutput;
use App\Entity\Category;
use App\Exception\CategoryHasChildrenException;
use App\Exception\CategoryNotFoundException;
use App\Exception\CircularReferenceException;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTree(bool $enabledOnly = false): array
    {
        $repository = $this->entityManager->getRepository(Category::class);
        $categories = $repository->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']);

        return $this->buildTree($categories, $enabledOnly);
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

        $this->entityManager->persist($category);
        $this->entityManager->flush();

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
            // sortOrder is always set (default 0), only update if changed
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

        $this->entityManager->flush();

        return $category;
    }

    public function delete(int $id): void
    {
        $category = $this->findCategory($id);

        if (!$category->getChildren()->isEmpty()) {
            throw new CategoryHasChildrenException();
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    public function toggle(int $id, bool $isEnabled): Category
    {
        $category = $this->findCategory($id);
        $category->setIsEnabled($isEnabled);
        $this->entityManager->flush();

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
        $this->entityManager->flush();

        return $category;
    }

    private function findCategory(int $id): Category
    {
        $category = $this->entityManager->getRepository(Category::class)->find($id);
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
        $ancestorIds = [];
        $repository = $this->entityManager->getRepository(Category::class);
        $category = $repository->find($categoryId);

        while (null !== $category && null !== $category->getParent()) {
            $parent = $category->getParent();
            $ancestorIds[] = $parent->getId();
            $category = $parent;
        }

        return $ancestorIds;
    }

    /**
     * @param Category[] $categories
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $categories, bool $enabledOnly): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if (null === $category->getParent()) {
                if ($enabledOnly && !$category->isEnabled()) {
                    continue;
                }
                $tree[] = $this->buildNode($category, $enabledOnly);
            }
        }

        return $tree;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNode(Category $category, bool $enabledOnly): array
    {
        $output = CategoryOutput::fromEntity($category)->toArray();

        $children = [];
        foreach ($category->getChildren() as $child) {
            if ($enabledOnly && !$child->isEnabled()) {
                continue;
            }
            $children[] = $this->buildNode($child, $enabledOnly);
        }
        $output['children'] = $children;

        return $output;
    }
}
