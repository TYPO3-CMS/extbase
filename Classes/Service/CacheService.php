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

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Cache clearing helper functions
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class CacheService implements SingletonInterface
{
    /**
     * @var \SplStack
     */
    protected $pageIdStack;

    /**
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     */
    protected $cacheManager;

    /**
     * @param \TYPO3\CMS\Core\Cache\CacheManager $cacheManager
     */
    public function injectCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Initializes the pageIdStack
     */
    public function __construct()
    {
        $this->pageIdStack = new \SplStack();
    }

    /**
     * @return \SplStack
     */
    public function getPageIdStack(): \SplStack
    {
        return $this->pageIdStack;
    }

    /**
     * Clears the page cache
     *
     * @param int|int[] $pageIdsToClear single or multiple pageIds to clear the cache for
     */
    public function clearPageCache($pageIdsToClear = null): void
    {
        if ($pageIdsToClear === null) {
            $this->cacheManager->flushCachesInGroup('pages');
        } else {
            if (!is_array($pageIdsToClear)) {
                $pageIdsToClear = [(int)$pageIdsToClear];
            }
            $tags = array_map(function ($item) {
                return 'pageId_' . $item;
            }, $pageIdsToClear);
            $this->cacheManager->flushCachesInGroupByTags('pages', $tags);
        }
    }

    /**
     * Walks through the pageIdStack, collects all pageIds
     * as array and passes them on to clearPageCache.
     */
    public function clearCachesOfRegisteredPageIds(): void
    {
        if (!$this->pageIdStack->isEmpty()) {
            $pageIds = [];
            while (!$this->pageIdStack->isEmpty()) {
                $pageIds[] = (int)$this->pageIdStack->pop();
            }
            $pageIds = array_values(array_unique($pageIds));
            $this->clearPageCache($pageIds);
        }
    }
}
