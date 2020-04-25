<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Extbase\Tests\Functional\Mvc\Controller\Fixture\Validation\Validator;

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

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validator which always fails
 */
class FailingValidator extends AbstractValidator
{
    /**
     * @param mixed $value
     */
    protected function isValid($value)
    {
        $this->addError('This will always fail', 157882910);
    }
}
