<?php

declare(strict_types=1);

namespace App\Dto;

readonly class CategoryInput
{
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public int $sortOrder = 0,
        public ?string $icon = null,
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
        public ?string $seoKeywords = null,
        public ?bool $isEnabled = null,
    ) {
    }
}
