<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Configuration;

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
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class BackendConfigurationManagerTest extends UnitTestCase
{
    /**
     * @var \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $backendConfigurationManager;

    /**
     * @var \TYPO3\CMS\Core\TypoScript\TypoScriptService|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $mockTypoScriptService;

    /**
     * Sets up this testcase
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->backendConfigurationManager = $this->getAccessibleMock(
            \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager::class,
            ['getTypoScriptSetup'],
            [],
            '',
            false
        );
        $this->mockTypoScriptService = $this->getAccessibleMock(\TYPO3\CMS\Core\TypoScript\TypoScriptService::class);
        $this->backendConfigurationManager->_set('typoScriptService', $this->mockTypoScriptService);
    }

    /**
     * @test
     */
    public function getCurrentPageIdReturnsPageIdFromGet()
    {
        $_GET['id'] = 123;
        $expectedResult = 123;
        $actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getCurrentPageIdReturnsPageIdFromPost()
    {
        $_GET['id'] = 123;
        $_POST['id'] = 321;
        $expectedResult = 321;
        $actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getPluginConfigurationReturnsEmptyArrayIfNoPluginConfigurationWasFound()
    {
        $this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue(['foo' => 'bar']));
        $expectedResult = [];
        $actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getPluginConfigurationReturnsExtensionConfiguration()
    {
        $testSettings = [
            'settings.' => [
                'foo' => 'bar'
            ]
        ];
        $testSettingsConverted = [
            'settings' => [
                'foo' => 'bar'
            ]
        ];
        $testSetup = [
            'module.' => [
                'tx_someextensionname.' => $testSettings
            ]
        ];
        $this->mockTypoScriptService->expects($this->any())->method('convertTypoScriptArrayToPlainArray')->with($testSettings)->will($this->returnValue($testSettingsConverted));
        $this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue($testSetup));
        $expectedResult = [
            'settings' => [
                'foo' => 'bar'
            ]
        ];
        $actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getPluginConfigurationReturnsPluginConfiguration()
    {
        $testSettings = [
            'settings.' => [
                'foo' => 'bar'
            ]
        ];
        $testSettingsConverted = [
            'settings' => [
                'foo' => 'bar'
            ]
        ];
        $testSetup = [
            'module.' => [
                'tx_someextensionname_somepluginname.' => $testSettings
            ]
        ];
        $this->mockTypoScriptService->expects($this->any())->method('convertTypoScriptArrayToPlainArray')->with($testSettings)->will($this->returnValue($testSettingsConverted));
        $this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue($testSetup));
        $expectedResult = [
            'settings' => [
                'foo' => 'bar'
            ]
        ];
        $actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getPluginConfigurationRecursivelyMergesExtensionAndPluginConfiguration()
    {
        $testExtensionSettings = [
            'settings.' => [
                'foo' => 'bar',
                'some.' => [
                    'nested' => 'value'
                ]
            ]
        ];
        $testExtensionSettingsConverted = [
            'settings' => [
                'foo' => 'bar',
                'some' => [
                    'nested' => 'value'
                ]
            ]
        ];
        $testPluginSettings = [
            'settings.' => [
                'some.' => [
                    'nested' => 'valueOverridde',
                    'new' => 'value'
                ]
            ]
        ];
        $testPluginSettingsConverted = [
            'settings' => [
                'some' => [
                    'nested' => 'valueOverridde',
                    'new' => 'value'
                ]
            ]
        ];
        $testSetup = [
            'module.' => [
                'tx_someextensionname.' => $testExtensionSettings,
                'tx_someextensionname_somepluginname.' => $testPluginSettings
            ]
        ];
        $this->mockTypoScriptService->expects($this->at(0))->method('convertTypoScriptArrayToPlainArray')->with($testExtensionSettings)->will($this->returnValue($testExtensionSettingsConverted));
        $this->mockTypoScriptService->expects($this->at(1))->method('convertTypoScriptArrayToPlainArray')->with($testPluginSettings)->will($this->returnValue($testPluginSettingsConverted));
        $this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue($testSetup));
        $expectedResult = [
            'settings' => [
                'foo' => 'bar',
                'some' => [
                    'nested' => 'valueOverridde',
                    'new' => 'value'
                ]
            ]
        ];
        $actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getControllerConfigurationReturnsEmptyArrayByDefault()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase'] = null;
        $expectedResult = [];
        $actualResult = $this->backendConfigurationManager->_call('getControllerConfiguration', 'SomeExtensionName', 'SomePluginName');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function getControllerConfigurationReturnsConfigurationStoredInExtconf()
    {
        $controllerConfiguration = [
            'Controller1' => [
                'actions' => [
                    'action1',
                    'action2'
                ],
                'nonCacheableActions' => [
                    'action1'
                ]
            ],
            'Controller2' => [
                'actions' => [
                    'action3',
                    'action4'
                ]
            ]
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['SomeExtensionName']['modules']['SomePluginName']['controllers'] = $controllerConfiguration;
        $expectedResult = $controllerConfiguration;
        $actualResult = $this->backendConfigurationManager->_call('getControllerConfiguration', 'SomeExtensionName', 'SomePluginName');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function storagePidsAreExtendedIfRecursiveSearchIsConfigured()
    {
        $storagePids = [1, 2, 3];
        $recursive = 99;

        /** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|ObjectProphecy $beUserAuthentication */
        $beUserAuthentication = $this->prophesize(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
        $beUserAuthentication->getPagePermsClause(1)->willReturn('1=1');
        $GLOBALS['BE_USER'] = $beUserAuthentication->reveal();

        $abstractConfigurationManager = $this->getAccessibleMock(
            \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager::class,
            ['overrideControllerConfigurationWithSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getControllerConfiguration'],
            [],
            '',
            false
        );
        $queryGenerator = $this->createMock(\TYPO3\CMS\Core\Database\QueryGenerator::class);
        $queryGenerator->expects($this->any())
            ->method('getTreeList')
            ->will($this->onConsecutiveCalls('4', '', '5,6'));
        GeneralUtility::addInstance(QueryGenerator::class, $queryGenerator);

        $expectedResult = [4, 5, 6];
        $actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePids, $recursive);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function storagePidsAreExtendedIfRecursiveSearchIsConfiguredAndWithPidIncludedForNegativePid()
    {
        $storagePids = [1, 2, -3];
        $recursive = 99;

        /** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication|ObjectProphecy $beUserAuthentication */
        $beUserAuthentication = $this->prophesize(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
        $beUserAuthentication->getPagePermsClause(1)->willReturn('1=1');
        $GLOBALS['BE_USER'] = $beUserAuthentication->reveal();

        $abstractConfigurationManager = $this->getAccessibleMock(
            \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager::class,
            ['overrideControllerConfigurationWithSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getControllerConfiguration', 'getQueryGenerator'],
            [],
            '',
            false
        );
        $queryGenerator = $this->createMock(\TYPO3\CMS\Core\Database\QueryGenerator::class);
        $queryGenerator->expects($this->any())
            ->method('getTreeList')
            ->will($this->onConsecutiveCalls('4', '', '3,5,6'));
        GeneralUtility::addInstance(QueryGenerator::class, $queryGenerator);

        $expectedResult = [4, 3, 5, 6];
        $actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePids, $recursive);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function storagePidsAreNotExtendedIfRecursiveSearchIsNotConfigured()
    {
        $storagePids = [1, 2, 3];

        $abstractConfigurationManager = $this->getAccessibleMock(
            \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager::class,
            ['overrideControllerConfigurationWithSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getControllerConfiguration'],
            [],
            '',
            false
        );

        $expectedResult = [1, 2, 3];
        $actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePids);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function storagePidsAreNotExtendedIfRecursiveSearchIsConfiguredForZeroLevels()
    {
        $storagePids = [1, 2, 3];
        $recursive = 0;

        $abstractConfigurationManager = $this->getAccessibleMock(
            \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager::class,
            ['overrideControllerConfigurationWithSwitchableControllerActions', 'getContextSpecificFrameworkConfiguration', 'getTypoScriptSetup', 'getPluginConfiguration', 'getControllerConfiguration'],
            [],
            '',
            false
        );

        $expectedResult = [1, 2, 3];
        $actualResult = $abstractConfigurationManager->_call('getRecursiveStoragePids', $storagePids, $recursive);
        $this->assertEquals($expectedResult, $actualResult);
    }
}
