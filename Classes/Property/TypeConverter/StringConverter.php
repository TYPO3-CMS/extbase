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

namespace TYPO3\CMS\Extbase\Property\TypeConverter;

use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;

/**
 * Converter which transforms simple types to a string.
 */
class StringConverter extends AbstractTypeConverter
{
    /**
     * @var string[]
     * @deprecated will be removed in TYPO3 v13.0, as this is defined in Services.yaml.
     */
    protected $sourceTypes = ['string', 'integer'];

    /**
     * @var string
     * @deprecated will be removed in TYPO3 v13.0, as this is defined in Services.yaml.
     */
    protected $targetType = 'string';

    /**
     * @var int
     * @deprecated will be removed in TYPO3 v13.0, as this is defined in Services.yaml.
     */
    protected $priority = 10;

    /**
     * Actually convert from $source to $targetType, taking into account the fully
     * built $convertedChildProperties and $configuration.
     *
     * @param string $source
     * @return string
     */
    public function convertFrom($source, string $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null): string
    {
        return (string)$source;
    }
}
