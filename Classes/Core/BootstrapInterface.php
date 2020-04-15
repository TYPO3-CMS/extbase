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

namespace TYPO3\CMS\Extbase\Core;

/**
 * Bootstrap Interface
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
interface BootstrapInterface
{
    /**
     * Runs the the Extbase Framework by resolving an appropriate Request Handler and passing control to it.
     * If the Framework is not initialized yet, it will be initialized.
     *
     * @param string $content The content. Not used
     * @param array $configuration The TS configuration array
     * @return string $content The processed content
     */
    public function run(string $content, array $configuration): string;
}
