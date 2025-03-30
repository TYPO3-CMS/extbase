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

namespace TYPO3Tests\BlogExample\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3Tests\BlogExample\Domain\Model\DateTimeImmutableExample;

/**
 * A repository for tx_blogexample_domain_model_datetimeimmutableexample
 * @extends Repository<DateTimeImmutableExample>
 */
class DateTimeImmutableExampleRepository extends Repository {}
