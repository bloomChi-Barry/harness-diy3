<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\CategoryHasChildrenException;
use App\Exception\CategoryNotFoundException;
use App\Exception\CircularReferenceException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $statusCode = match (true) {
            $exception instanceof CategoryNotFoundException => 404,
            $exception instanceof CategoryHasChildrenException => 409,
            $exception instanceof CircularReferenceException => 422,
            $exception instanceof \InvalidArgumentException => 400,
            $exception instanceof \JsonException => 400,
            default => 500,
        };

        $errorCode = match (true) {
            $exception instanceof CategoryNotFoundException => CategoryNotFoundException::ERROR_CODE,
            $exception instanceof CategoryHasChildrenException => CategoryHasChildrenException::ERROR_CODE,
            $exception instanceof CircularReferenceException => CircularReferenceException::ERROR_CODE,
            $exception instanceof \InvalidArgumentException => 'VALIDATION_ERROR',
            $exception instanceof \JsonException => 'INVALID_JSON',
            default => 'INTERNAL_ERROR',
        };

        $response = new JsonResponse([
            'error' => [
                'code' => $errorCode,
                'message' => $exception->getMessage(),
            ],
        ], $statusCode);

        $event->setResponse($response);
    }
}
