<?php
declare(strict_types=1);

namespace TYPO3\CMS\Extbase\Tests\Unit\Reflection\Fixture;

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

use TYPO3\CMS\Extbase\Annotation as Extbase;

/**
 * Fixture class with @TYPO3\CMS\Extbase\Annotation\ORM\Lazy annotation
 */
class DummyClassWithLazyDoctrineAnnotation
{
    /**
     * @Extbase\ORM\Lazy
     */
    public $propertyWithLazyAnnotation;
}
