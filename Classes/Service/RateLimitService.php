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

namespace TYPO3\CMS\Extbase\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\RateLimiter\Storage\CachingFrameworkStorage;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Attribute\RateLimit;

/**
 * @internal Only to be used within Extbase, not part of TYPO3 Core API.
 */
class RateLimitService implements SingletonInterface
{
    public function __construct(protected readonly CachingFrameworkStorage $cachingFrameworkStorage) {}

    public function isRequestRateLimited(
        ServerRequestInterface $request,
        string $identifier,
        RateLimit $rateLimit
    ): bool {
        $rateLimiter = $this->getRateLimiter(
            $request,
            $identifier,
            $rateLimit
        );

        $limit = $rateLimiter->consume();
        return !$limit->isAccepted();
    }

    protected function getRateLimiter(
        ServerRequestInterface $request,
        string $identifier,
        RateLimit $rateLimit
    ): LimiterInterface {
        $config = [
            'id' => 'extbase-' . $identifier,
            'policy' => $rateLimit->policy,
            'limit' => $rateLimit->limit,
            'interval' => $rateLimit->interval,
        ];

        $normalizedParams = $request->getAttribute('normalizedParams') ?? NormalizedParams::createFromRequest($request);
        $remoteIp = $normalizedParams->getRemoteAddress();

        $limiterFactory = new RateLimiterFactory(
            $config,
            $this->cachingFrameworkStorage
        );
        return $limiterFactory->create($remoteIp);
    }
}
