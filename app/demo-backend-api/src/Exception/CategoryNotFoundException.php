<?php

declare(strict_types=1);

namespace App\Exception;

class CategoryNotFoundException extends \RuntimeException
{
    public const ERROR_CODE = 'NOT_FOUND';

    public function __construct(string $message = 'Category not found')
    {
        parent::__construct($message);
    }
}
