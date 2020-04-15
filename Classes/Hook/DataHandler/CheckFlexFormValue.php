<?php

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

namespace TYPO3\CMS\Extbase\Hook\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * @internal this is not part of TYPO3 Core API as it is a concrete hook implementation.
 * @deprecated since TYPO3 v10, will be removed when support for switchable controller actions is removed
 */
class CheckFlexFormValue
{
    /**
     * Check flexform value before merge
     *
     * @param DataHandler $dataHandler
     * @param array $currentValue
     * @param array $newValue
     */
    public function checkFlexFormValue_beforeMerge(DataHandler $dataHandler, array &$currentValue, array &$newValue)
    {
        $currentValue = $this->removeSwitchableControllerActionsRecursive($currentValue);
    }

    /**
     * Remove switchable controller actions recursively
     *
     * @param array $a
     * @return array
     */
    protected function removeSwitchableControllerActionsRecursive(array $a)
    {
        $r = [];

        foreach ($a as $k => $v) {
            if ($k === 'switchableControllerActions') {
                continue;
            }

            $r[$k] = is_array($v) ? $this->removeSwitchableControllerActionsRecursive($v) : $v;
        }

        return $r;
    }
}
