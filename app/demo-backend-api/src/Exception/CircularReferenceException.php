<?php

declare(strict_types=1);

namespace App\Exception;

class CircularReferenceException extends \RuntimeException
{
    public const ERROR_CODE = 'CIRCULAR_REFERENCE';

    public function __construct(string $message = 'Cannot move category to itself or its own descendant')
    {
        parent::__construct($message);
    }
}
