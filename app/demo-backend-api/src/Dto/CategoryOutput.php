<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Category;

readonly class CategoryOutput
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    public static function fromEntity(Category $category): self
    {
        $children = $category->getChildren()->map(
            fn (Category $child) => self::fromEntity($child)->toArray()
        )->toArray();

        $data = [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'parent_id' => $category->getParent()?->getId(),
            'sort_order' => $category->getSortOrder(),
            'icon' => $category->getIcon(),
            'seo_title' => $category->getSeoTitle(),
            'seo_description' => $category->getSeoDescription(),
            'seo_keywords' => $category->getSeoKeywords(),
            'is_enabled' => $category->isEnabled(),
            'created_at' => $category->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at' => $category->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'children' => array_values($children),
        ];

        return new self($data);
    }

    public static function fromEntityShallow(Category $category): self
    {
        $children = $category->getChildren()->map(
            fn (Category $child) => ['id' => $child->getId(), 'name' => $child->getName()]
        )->toArray();

        $data = [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'parent_id' => $category->getParent()?->getId(),
            'sort_order' => $category->getSortOrder(),
            'icon' => $category->getIcon(),
            'seo_title' => $category->getSeoTitle(),
            'seo_description' => $category->getSeoDescription(),
            'seo_keywords' => $category->getSeoKeywords(),
            'is_enabled' => $category->isEnabled(),
            'created_at' => $category->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at' => $category->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'children' => array_values($children),
        ];

        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
