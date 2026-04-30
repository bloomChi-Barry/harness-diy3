<?php

declare(strict_types=1);

namespace App\Exception;

class CategoryHasChildrenException extends \RuntimeException
{
    public const ERROR_CODE = 'HAS_CHILDREN';

    public function __construct(string $message = 'Category has children and cannot be deleted')
    {
        parent::__construct($message);
    }
}
