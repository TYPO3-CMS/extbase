<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
 *  All rights reserved
 *
 *  This class is a backport of the corresponding class of FLOW3.
 *  All credits go to the v5 team.
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
class BackendConfigurationManagerTest extends \TYPO3\CMS\Extbase\Tests\Unit\BaseTestCase {

	/**
	 * Enable backup of global and system variables
	 *
	 * @var boolean
	 */
	protected $backupGlobals = TRUE;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $typo3DbBackup;

	/**
	 * @var \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager
	 */
	protected $backendConfigurationManager;

	/**
	 * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
	 */
	protected $mockTypoScriptService;

	/**
	 * Sets up this testcase
	 */
	public function setUp() {
		$this->typo3DbBackup = $GLOBALS['TYPO3_DB'];
		$GLOBALS['TYPO3_DB'] = $this->getMock('TYPO3\\CMS\\Core\\Database\\DatabaseConnection', array());
		$this->backendConfigurationManager = $this->getAccessibleMock('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager', array('getTypoScriptSetup'));
		$this->mockTypoScriptService = $this->getAccessibleMock('TYPO3\\CMS\\Extbase\\Service\\TypoScriptService');
		$this->backendConfigurationManager->injectTypoScriptService($this->mockTypoScriptService);
	}

	/**
	 * Tears down this testcase
	 */
	public function tearDown() {
		$GLOBALS['TYPO3_DB'] = $this->typo3DbBackup;
	}

	/**
	 * @test
	 */
	public function getTypoScriptSetupCanBeTested() {
		$this->markTestIncomplete('This method can\'t be tested with the current TYPO3 version, because we can\'t mock objects returned from TYPO3\\CMS\\Core\\Utility\\GeneralUtility::makeInstance().');
	}

	/**
	 * @test
	 */
	public function getCurrentPageIdReturnsPageIdFromGet() {
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GETset(array('id' => 123));
		$expectedResult = 123;
		$actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getCurrentPageIdReturnsPageIdFromPost() {
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GETset(array('id' => 123));
		$_POST['id'] = 321;
		$expectedResult = 321;
		$actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getCurrentPageIdReturnsPidFromFirstRootTemplateIfIdIsNotSetAndNoRootPageWasFound() {
		$GLOBALS['TYPO3_DB']->expects($this->at(0))->method('exec_SELECTgetRows')->with('uid', 'pages', 'deleted=0 AND hidden=0 AND is_siteroot=1', '', '', '1')->will($this->returnValue(array()));
		$GLOBALS['TYPO3_DB']->expects($this->at(1))->method('exec_SELECTgetRows')->with('pid', 'sys_template', 'deleted=0 AND hidden=0 AND root=1', '', '', '1')->will($this->returnValue(array(
			array('pid' => 123)
		)));
		$expectedResult = 123;
		$actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getCurrentPageIdReturnsUidFromFirstRootPageIfIdIsNotSet() {
		$GLOBALS['TYPO3_DB']->expects($this->once())->method('exec_SELECTgetRows')->with('uid', 'pages', 'deleted=0 AND hidden=0 AND is_siteroot=1', '', '', '1')->will($this->returnValue(array(
			array('uid' => 321)
		)));
		$expectedResult = 321;
		$actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getCurrentPageIdReturnsDefaultStoragePidIfIdIsNotSetNoRootTemplateAndRootPageWasFound() {
		$GLOBALS['TYPO3_DB']->expects($this->at(0))->method('exec_SELECTgetRows')->with('uid', 'pages', 'deleted=0 AND hidden=0 AND is_siteroot=1', '', '', '1')->will($this->returnValue(array()));
		$GLOBALS['TYPO3_DB']->expects($this->at(1))->method('exec_SELECTgetRows')->with('pid', 'sys_template', 'deleted=0 AND hidden=0 AND root=1', '', '', '1')->will($this->returnValue(array()));
		$expectedResult = \TYPO3\CMS\Extbase\Configuration\AbstractConfigurationManager::DEFAULT_BACKEND_STORAGE_PID;
		$actualResult = $this->backendConfigurationManager->_call('getCurrentPageId');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationReturnsEmptyArrayIfNoPluginConfigurationWasFound() {
		$this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue(array('foo' => 'bar')));
		$expectedResult = array();
		$actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationReturnsExtensionConfiguration() {
		$testSettings = array(
			'settings.' => array(
				'foo' => 'bar'
			)
		);
		$testSettingsConverted = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$testSetup = array(
			'module.' => array(
				'tx_someextensionname.' => $testSettings
			)
		);
		$this->mockTypoScriptService->expects($this->any())->method('convertTypoScriptArrayToPlainArray')->with($testSettings)->will($this->returnValue($testSettingsConverted));
		$this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue($testSetup));
		$expectedResult = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationReturnsPluginConfiguration() {
		$testSettings = array(
			'settings.' => array(
				'foo' => 'bar'
			)
		);
		$testSettingsConverted = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$testSetup = array(
			'module.' => array(
				'tx_someextensionname_somepluginname.' => $testSettings
			)
		);
		$this->mockTypoScriptService->expects($this->any())->method('convertTypoScriptArrayToPlainArray')->with($testSettings)->will($this->returnValue($testSettingsConverted));
		$this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue($testSetup));
		$expectedResult = array(
			'settings' => array(
				'foo' => 'bar'
			)
		);
		$actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getPluginConfigurationRecursivelyMergesExtensionAndPluginConfiguration() {
		$testExtensionSettings = array(
			'settings.' => array(
				'foo' => 'bar',
				'some.' => array(
					'nested' => 'value'
				)
			)
		);
		$testExtensionSettingsConverted = array(
			'settings' => array(
				'foo' => 'bar',
				'some' => array(
					'nested' => 'value'
				)
			)
		);
		$testPluginSettings = array(
			'settings.' => array(
				'some.' => array(
					'nested' => 'valueOverridde',
					'new' => 'value'
				)
			)
		);
		$testPluginSettingsConverted = array(
			'settings' => array(
				'some' => array(
					'nested' => 'valueOverridde',
					'new' => 'value'
				)
			)
		);
		$testSetup = array(
			'module.' => array(
				'tx_someextensionname.' => $testExtensionSettings,
				'tx_someextensionname_somepluginname.' => $testPluginSettings
			)
		);
		$this->mockTypoScriptService->expects($this->at(0))->method('convertTypoScriptArrayToPlainArray')->with($testExtensionSettings)->will($this->returnValue($testExtensionSettingsConverted));
		$this->mockTypoScriptService->expects($this->at(1))->method('convertTypoScriptArrayToPlainArray')->with($testPluginSettings)->will($this->returnValue($testPluginSettingsConverted));
		$this->backendConfigurationManager->expects($this->once())->method('getTypoScriptSetup')->will($this->returnValue($testSetup));
		$expectedResult = array(
			'settings' => array(
				'foo' => 'bar',
				'some' => array(
					'nested' => 'valueOverridde',
					'new' => 'value'
				)
			)
		);
		$actualResult = $this->backendConfigurationManager->_call('getPluginConfiguration', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getSwitchableControllerActionsReturnsEmptyArrayByDefault() {
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase'] = NULL;
		$expectedResult = array();
		$actualResult = $this->backendConfigurationManager->_call('getSwitchableControllerActions', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getSwitchableControllerActionsReturnsConfigurationStoredInExtconf() {
		$testSwitchableControllerActions = array(
			'Controller1' => array(
				'actions' => array(
					'action1',
					'action2'
				),
				'nonCacheableActions' => array(
					'action1'
				)
			),
			'Controller2' => array(
				'actions' => array(
					'action3',
					'action4'
				)
			)
		);
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['SomeExtensionName']['modules']['SomePluginName']['controllers'] = $testSwitchableControllerActions;
		$expectedResult = $testSwitchableControllerActions;
		$actualResult = $this->backendConfigurationManager->_call('getSwitchableControllerActions', 'SomeExtensionName', 'SomePluginName');
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getContextSpecificFrameworkConfigurationReturnsUnmodifiedFrameworkConfigurationIfRequestHandlersAreConfigured() {
		$frameworkConfiguration = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'foo' => array(
				'bar' => array(
					'baz' => 'Foo'
				)
			),
			'mvc' => array(
				'requestHandlers' => array(
					'TYPO3\\CMS\\Extbase\\Mvc\\Web\\FrontendRequestHandler' => 'SomeRequestHandler'
				)
			)
		);
		$expectedResult = $frameworkConfiguration;
		$actualResult = $this->backendConfigurationManager->_call('getContextSpecificFrameworkConfiguration', $frameworkConfiguration);
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function getContextSpecificFrameworkConfigurationSetsDefaultRequestHandlersIfRequestHandlersAreNotConfigured() {
		$frameworkConfiguration = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'foo' => array(
				'bar' => array(
					'baz' => 'Foo'
				)
			)
		);
		$expectedResult = array(
			'pluginName' => 'Pi1',
			'extensionName' => 'SomeExtension',
			'foo' => array(
				'bar' => array(
					'baz' => 'Foo'
				)
			),
			'mvc' => array(
				'requestHandlers' => array(
					'TYPO3\\CMS\\Extbase\\Mvc\\Web\\FrontendRequestHandler' => 'TYPO3\\CMS\\Extbase\\Mvc\\Web\\FrontendRequestHandler',
					'TYPO3\\CMS\\Extbase\\Mvc\\Web\\BackendRequestHandler' => 'TYPO3\\CMS\\Extbase\\Mvc\\Web\\BackendRequestHandler'
				)
			)
		);
		$actualResult = $this->backendConfigurationManager->_call('getContextSpecificFrameworkConfiguration', $frameworkConfiguration);
		$this->assertEquals($expectedResult, $actualResult);
	}

}


?>