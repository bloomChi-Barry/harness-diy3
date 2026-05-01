<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(int $id): ?Category
    {
        return $this->entityManager->getRepository(Category::class)->find($id);
    }

    public function findAllOrdered(): array
    {
        return $this->entityManager->getRepository(Category::class)->findBy(
            [],
            ['sortOrder' => 'ASC', 'id' => 'ASC']
        );
    }

    public function findAncestorIds(int $categoryId): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT c.id, IDENTITY(c.parent) AS parent_id FROM App\Entity\Category c'
        )->getArrayResult();

        $parentOf = [];
        foreach ($rows as $row) {
            if (null !== $row['parent_id']) {
                $parentOf[(int) $row['id']] = (int) $row['parent_id'];
            }
        }

        $ancestorIds = [];
        $currentId = $categoryId;
        while (isset($parentOf[$currentId])) {
            $parentId = $parentOf[$currentId];
            $ancestorIds[] = $parentId;
            $currentId = $parentId;
        }

        return $ancestorIds;
    }

    public function save(Category $category): void
    {
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    public function remove(Category $category): void
    {
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
