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

namespace TYPO3Tests\TestValidators\Validation\Validator;

use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Fixture to verify symfony DI works for validator and options are set.
 */
class CustomValidator extends AbstractValidator
{
    protected $supportedOptions = [
        'foo' => [0, 'foo length', 'integer'],
    ];

    public IconFactory $iconFactory;

    public function __construct(IconFactory $iconFactory)
    {
        $this->iconFactory = $iconFactory;
    }

    protected function isValid(mixed $value): void {}
}
