<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    /**
     * @return Category[]
     */
    public function findAllOrdered(): array;

    /**
     * @return int[]
     */
    public function findAncestorIds(int $categoryId): array;

    public function save(Category $category): void;

    public function remove(Category $category): void;
}
