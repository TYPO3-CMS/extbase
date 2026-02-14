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

namespace TYPO3\CMS\Extbase\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Attribute\RateLimit;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RateLimitTest extends UnitTestCase
{
    #[Test]
    public function constructorThrowsExceptionForInvalidLimit(): void
    {
        $this->expectExceptionObject(
            new \RuntimeException('Invalid "limit" property for rate limit. Ensure, that the value is greater than 0.', 1771074438),
        );

        new RateLimit(0);
    }

    #[Test]
    public function constructorThrowsExceptionForInvalidInterval(): void
    {
        $this->expectExceptionObject(
            new \RuntimeException('Invalid "interval" property for rate limit.', 1771074439),
        );

        new RateLimit(5, '');
    }

    #[Test]
    public function constructorThrowsExceptionForInvalidPolicy(): void
    {
        $this->expectExceptionObject(
            new \RuntimeException('Invalid "policy" property for rate limit.', 1771074440),
        );

        new RateLimit(5, '10 minutes', '');
    }
}
