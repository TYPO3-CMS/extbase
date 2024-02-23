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

namespace TYPO3\CMS\Extbase\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CategoryTest extends UnitTestCase
{
    #[Test]
    public function getTitleInitiallyReturnsEmptyString(): void
    {
        $subject = new Category();
        self::assertSame('', $subject->getTitle());
    }

    #[Test]
    public function setTitleSetsTitle(): void
    {
        $subject = new Category();
        $subject->setTitle('foo bar');
        self::assertSame('foo bar', $subject->getTitle());
    }

    #[Test]
    public function getDescriptionInitiallyReturnsEmptyString(): void
    {
        $subject = new Category();
        self::assertSame('', $subject->getDescription());
    }

    #[Test]
    public function setDescriptionSetsDescription(): void
    {
        $subject = new Category();
        $subject->setDescription('foo bar');
        self::assertSame('foo bar', $subject->getDescription());
    }

    #[Test]
    public function getParentInitiallyReturnsNull(): void
    {
        $subject = new Category();
        self::assertNull($subject->getParent());
    }

    #[Test]
    public function setParentSetsParent(): void
    {
        $parent = new Category();
        $subject = new Category();
        $subject->setParent($parent);
        self::assertSame($parent, $subject->getParent());
    }
}
