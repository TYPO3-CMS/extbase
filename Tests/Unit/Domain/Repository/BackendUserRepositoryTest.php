<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Domain\Repository;

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

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class BackendUserRepositoryTest extends UnitTestCase
{
    /**
     * @test
     */
    public function initializeObjectSetsRespectStoragePidToFalse()
    {
        $objectManager = $this->createMock(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface::class);
        $fixture = new \TYPO3\CMS\Extbase\Domain\Repository\BackendUserGroupRepository($objectManager);
        $querySettings = $this->createMock(\TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class);
        $querySettings->expects(self::once())->method('setRespectStoragePage')->with(false);
        $objectManager->expects(self::once())->method('get')->with(\TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class)->willReturn($querySettings);
        $fixture->initializeObject();
    }

    /**
     * @test
     */
    public function initializeObjectSetsDefaultQuerySettings()
    {
        $objectManager = $this->createMock(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface::class);
        /** @var $fixture \TYPO3\CMS\Extbase\Domain\Repository\BackendUserGroupRepository */
        $fixture = $this->getMockBuilder(\TYPO3\CMS\Extbase\Domain\Repository\BackendUserGroupRepository::class)
            ->setMethods(['setDefaultQuerySettings'])
            ->setConstructorArgs([$objectManager])
            ->getMock();
        $querySettings = $this->createMock(\TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class);
        $objectManager->expects(self::once())->method('get')->with(\TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class)->willReturn($querySettings);
        $fixture->expects(self::once())->method('setDefaultQuerySettings')->with($querySettings);
        $fixture->initializeObject();
    }
}
