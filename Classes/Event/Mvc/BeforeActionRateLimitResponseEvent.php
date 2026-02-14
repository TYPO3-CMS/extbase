<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Extbase\Event\Mvc;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Attribute\RateLimit;

/**
 * Event that is triggered, when an extbase action is rate limitied and before the rate limit response is sent.
 * Extension developers can use this event to provide an alternative response or to implement a custom logic.
 */
final class BeforeActionRateLimitResponseEvent
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly string $controllerClassName,
        private readonly string $actionMethodName,
        private readonly RateLimit $rateLimit,
        private ResponseInterface $response
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getControllerClassName(): string
    {
        return $this->controllerClassName;
    }

    public function getActionMethodName(): string
    {
        return $this->actionMethodName;
    }

    public function getRateLimit(): RateLimit
    {
        return $this->rateLimit;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
