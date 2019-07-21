<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Extbase\Tests\Unit\Mvc\Web\Routing;

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

use PHPUnit\Framework\Error\Warning;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentValueException;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Extbase\Tests\Unit\Mvc\Web\Routing\Fixtures\EntityFixture;
use TYPO3\CMS\Extbase\Tests\Unit\Mvc\Web\Routing\Fixtures\ValueObjectFixture;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class UriBuilderTest extends UnitTestCase
{
    /**
     * @var bool Reset singletons created by subject
     */
    protected $resetSingletonInstances = true;

    /**
     * @var ConfigurationManagerInterface
     */
    protected $mockConfigurationManager;

    /**
     * @var ContentObjectRenderer|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockContentObject;

    /**
     * @var Request|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockRequest;

    /**
     * @var ExtensionService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockExtensionService;

    /**
     * @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $uriBuilder;

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TSFE'] = $this->createMock(TypoScriptFrontendController::class);
        $this->mockContentObject = $this->createMock(ContentObjectRenderer::class);
        $this->mockRequest = $this->createMock(Request::class);
        $this->mockExtensionService = $this->createMock(ExtensionService::class);
        $this->mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $this->uriBuilder = $this->getAccessibleMock(UriBuilder::class, ['build']);
        $this->uriBuilder->setRequest($this->mockRequest);
        $this->uriBuilder->_set('contentObject', $this->mockContentObject);
        $this->uriBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->uriBuilder->_set('extensionService', $this->mockExtensionService);
        $this->uriBuilder->_set('environmentService', $this->createMock(EnvironmentService::class));
        $router = GeneralUtility::makeInstance(Router::class);
        $router->addRoute('module_key', new Route('/test/Path', []));
        $router->addRoute('module_key2', new Route('/test/Path2', []));
        $router->addRoute('', new Route('', []));
    }

    /**
     * @test
     */
    public function settersAndGettersWorkAsExpected()
    {
        $this->uriBuilder
            ->reset()
            ->setArguments(['test' => 'arguments'])
            ->setSection('testSection')
            ->setFormat('testFormat')
            ->setCreateAbsoluteUri(true)
            ->setAbsoluteUriScheme('https')
            ->setAddQueryString(true)
            ->setArgumentsToBeExcludedFromQueryString(['test' => 'addQueryStringExcludeArguments'])
            ->setAddQueryStringMethod('GET')
            ->setArgumentPrefix('testArgumentPrefix')
            ->setLinkAccessRestrictedPages(true)
            ->setTargetPageUid(123)
            ->setTargetPageType(321)
            ->setNoCache(true);
        $this->assertEquals(['test' => 'arguments'], $this->uriBuilder->getArguments());
        $this->assertEquals('testSection', $this->uriBuilder->getSection());
        $this->assertEquals('testFormat', $this->uriBuilder->getFormat());
        $this->assertEquals(true, $this->uriBuilder->getCreateAbsoluteUri());
        $this->assertEquals('https', $this->uriBuilder->getAbsoluteUriScheme());
        $this->assertEquals(true, $this->uriBuilder->getAddQueryString());
        $this->assertEquals(['test' => 'addQueryStringExcludeArguments'], $this->uriBuilder->getArgumentsToBeExcludedFromQueryString());
        $this->assertEquals('GET', $this->uriBuilder->getAddQueryStringMethod());
        $this->assertEquals('testArgumentPrefix', $this->uriBuilder->getArgumentPrefix());
        $this->assertEquals(true, $this->uriBuilder->getLinkAccessRestrictedPages());
        $this->assertEquals(123, $this->uriBuilder->getTargetPageUid());
        $this->assertEquals(321, $this->uriBuilder->getTargetPageType());
        $this->assertEquals(true, $this->uriBuilder->getNoCache());
    }

    /**
     * @test
     */
    public function uriForPrefixesArgumentsWithExtensionAndPluginNameAndSetsControllerArgument()
    {
        $this->mockExtensionService->expects($this->once())->method('getPluginNamespace')->will($this->returnValue('tx_someextension_someplugin'));
        $expectedArguments = ['tx_someextension_someplugin' => ['foo' => 'bar', 'baz' => ['extbase' => 'fluid'], 'controller' => 'SomeController']];
        $GLOBALS['TSFE'] = null;
        $this->uriBuilder->uriFor(null, ['foo' => 'bar', 'baz' => ['extbase' => 'fluid']], 'SomeController', 'SomeExtension', 'SomePlugin');
        $this->assertEquals($expectedArguments, $this->uriBuilder->getArguments());
    }

    /**
     * @test
     */
    public function uriForRecursivelyMergesAndOverrulesControllerArgumentsWithArguments()
    {
        $this->mockExtensionService->expects($this->once())->method('getPluginNamespace')->will($this->returnValue('tx_someextension_someplugin'));
        $arguments = ['tx_someextension_someplugin' => ['foo' => 'bar'], 'additionalParam' => 'additionalValue'];
        $controllerArguments = ['foo' => 'overruled', 'baz' => ['extbase' => 'fluid']];
        $expectedArguments = ['tx_someextension_someplugin' => ['foo' => 'overruled', 'baz' => ['extbase' => 'fluid'], 'controller' => 'SomeController'], 'additionalParam' => 'additionalValue'];
        $this->uriBuilder->setArguments($arguments);
        $this->uriBuilder->uriFor(null, $controllerArguments, 'SomeController', 'SomeExtension', 'SomePlugin');
        $this->assertEquals($expectedArguments, $this->uriBuilder->getArguments());
    }

    /**
     * @test
     */
    public function uriForOnlySetsActionArgumentIfSpecified()
    {
        $this->mockExtensionService->expects($this->once())->method('getPluginNamespace')->will($this->returnValue('tx_someextension_someplugin'));
        $expectedArguments = ['tx_someextension_someplugin' => ['controller' => 'SomeController']];
        $this->uriBuilder->uriFor(null, [], 'SomeController', 'SomeExtension', 'SomePlugin');
        $this->assertEquals($expectedArguments, $this->uriBuilder->getArguments());
    }

    /**
     * @test
     */
    public function uriForSetsControllerFromRequestIfControllerIsNotSet()
    {
        $this->mockExtensionService->expects($this->once())->method('getPluginNamespace')->will($this->returnValue('tx_someextension_someplugin'));
        $this->mockRequest->expects($this->once())->method('getControllerName')->will($this->returnValue('SomeControllerFromRequest'));
        $expectedArguments = ['tx_someextension_someplugin' => ['controller' => 'SomeControllerFromRequest']];
        $this->uriBuilder->uriFor(null, [], null, 'SomeExtension', 'SomePlugin');
        $this->assertEquals($expectedArguments, $this->uriBuilder->getArguments());
    }

    /**
     * @test
     */
    public function uriForSetsExtensionNameFromRequestIfExtensionNameIsNotSet()
    {
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_someextensionnamefromrequest_someplugin'));
        $this->mockRequest->expects($this->once())->method('getControllerExtensionName')->will($this->returnValue('SomeExtensionNameFromRequest'));
        $expectedArguments = ['tx_someextensionnamefromrequest_someplugin' => ['controller' => 'SomeController']];
        $this->uriBuilder->uriFor(null, [], 'SomeController', null, 'SomePlugin');
        $this->assertEquals($expectedArguments, $this->uriBuilder->getArguments());
    }

    /**
     * @test
     */
    public function uriForSetsPluginNameFromRequestIfPluginNameIsNotSet()
    {
        $this->mockExtensionService->expects($this->once())->method('getPluginNamespace')->will($this->returnValue('tx_someextension_somepluginnamefromrequest'));
        $this->mockRequest->expects($this->once())->method('getPluginName')->will($this->returnValue('SomePluginNameFromRequest'));
        $expectedArguments = ['tx_someextension_somepluginnamefromrequest' => ['controller' => 'SomeController']];
        $this->uriBuilder->uriFor(null, [], 'SomeController', 'SomeExtension');
        $this->assertEquals($expectedArguments, $this->uriBuilder->getArguments());
    }

    /**
     * @test
     */
    public function buildBackendUriKeepsQueryParametersIfAddQueryStringIsSet()
    {
        $_GET['route'] = '/test/Path';
        $_GET['id'] = 'pageId';
        $_GET['foo'] = 'bar';
        $_POST = [];
        $this->uriBuilder->setAddQueryString(true);
        $this->uriBuilder->setAddQueryStringMethod('GET');
        $expectedResult = '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken&id=pageId&foo=bar';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildBackendUriKeepsQueryParametersIfAddQueryStringMethodIsNotSet()
    {
        $_GET['route'] = '/test/Path';
        $_GET['id'] = 'pageId';
        $_GET['foo'] = 'bar';
        $_POST = [];
        $_POST['foo2'] = 'bar2';
        $this->uriBuilder->setAddQueryString(true);
        $expectedResult = '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken&id=pageId&foo=bar';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * return array
     */
    public function buildBackendUriRemovesSpecifiedQueryParametersIfArgumentsToBeExcludedFromQueryStringIsSetDataProvider()
    {
        return [
            'Arguments to be excluded in the beginning' => [
                [
                    'route' => '/test/Path',
                    'id' => 'pageId',
                    'foo' => 'bar'
                ],
                [
                    'route',
                    'id'
                ],
                '/typo3/index.php?route=%2F&token=dummyToken&foo=bar'
            ],
            'Arguments to be excluded in the end' => [
                [
                    'foo' => 'bar',
                    'id' => 'pageId',
                    'route' => '/test/Path'
                ],
                [
                    'route',
                    'id'
                ],
                '/typo3/index.php?route=%2F&token=dummyToken&foo=bar'
            ],
            'Arguments in nested array to be excluded' => [
                [
                    'tx_foo' => [
                        'bar' => 'baz'
                    ],
                    'id' => 'pageId',
                    'route' => '/test/Path'
                ],
                [
                    'id',
                    'tx_foo[bar]'
                ],
                '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken'
            ],
            'Arguments in multidimensional array to be excluded' => [
                [
                    'tx_foo' => [
                        'bar' => [
                            'baz' => 'bay'
                        ]
                    ],
                    'id' => 'pageId',
                    'route' => '/test/Path'
                ],
                [
                    'id',
                    'tx_foo[bar][baz]'
                ],
                '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken'
            ],
        ];
    }

    /**
     * @test
     * @dataProvider buildBackendUriRemovesSpecifiedQueryParametersIfArgumentsToBeExcludedFromQueryStringIsSetDataProvider
     * @param array $parameters
     * @param array $excluded
     * @param string $expected
     */
    public function buildBackendUriRemovesSpecifiedQueryParametersIfArgumentsToBeExcludedFromQueryStringIsSet(array $parameters, array $excluded, $expected)
    {
        $_GET = array_replace_recursive($_GET, $parameters);
        $this->uriBuilder->setAddQueryString(true);
        $this->uriBuilder->setAddQueryStringMethod('GET');
        $this->uriBuilder->setArgumentsToBeExcludedFromQueryString($excluded);
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expected, $actualResult);
    }

    /**
     * @test
     */
    public function buildBackendUriKeepsModuleQueryParametersIfAddQueryStringIsNotSet()
    {
        $_GET = (['route' => '/test/Path', 'id' => 'pageId', 'foo' => 'bar']);
        $expectedResult = '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken&id=pageId';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildBackendUriMergesAndOverrulesQueryParametersWithArguments()
    {
        $_GET = ['route' => '/test/Path', 'id' => 'pageId', 'foo' => 'bar'];
        $this->uriBuilder->setArguments(['route' => '/test/Path2', 'somePrefix' => ['bar' => 'baz']]);
        $expectedResult = '/typo3/index.php?route=%2Ftest%2FPath2&token=dummyToken&id=pageId&somePrefix%5Bbar%5D=baz';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildBackendUriConvertsDomainObjectsAfterArgumentsHaveBeenMerged()
    {
        $_GET['route'] = '/test/Path';
        $mockDomainObject = $this->getAccessibleMock(AbstractEntity::class, ['dummy']);
        $mockDomainObject->_set('uid', '123');
        $this->uriBuilder->setArguments(['somePrefix' => ['someDomainObject' => $mockDomainObject]]);
        $expectedResult = '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken&somePrefix%5BsomeDomainObject%5D=123';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildBackendUriRespectsSection()
    {
        $_GET['route'] = '/test/Path';
        $this->uriBuilder->setSection('someSection');
        $expectedResult = '/typo3/index.php?route=%2Ftest%2FPath&token=dummyToken#someSection';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildBackendUriCreatesAbsoluteUrisIfSpecified()
    {
        $_GET['route'] = '/test/Path';
        $_SERVER['HTTP_HOST'] = 'baseuri';
        $_SERVER['SCRIPT_NAME'] = '/typo3/index.php';
        $_SERVER['ORIG_SCRIPT_NAME'] = '/typo3/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->mockRequest->expects($this->any())->method('getBaseUri')->will($this->returnValue('http://baseuri'));
        $this->uriBuilder->setCreateAbsoluteUri(true);
        $expectedResult = 'http://baseuri/' . TYPO3_mainDir . 'index.php?route=%2Ftest%2FPath&token=dummyToken';
        $actualResult = $this->uriBuilder->buildBackendUri();
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * @return array
     */
    public function buildUriDataProvider(): array
    {
        $uriPrefix = '/typo3/index.php?route=%2Ftest%2FPath';

        return [
            'GET,POST' => [
                'GET,POST',
                'Assigning addQueryStringMethod = GET,POST or POST,GET is not supported anymore since TYPO3 v10.0 - falling back to GET',
                $uriPrefix . '&common=GET&get=GET'
            ],
            'POST,GET' => [
                'POST,GET',
                'Assigning addQueryStringMethod = GET,POST or POST,GET is not supported anymore since TYPO3 v10.0 - falling back to GET',
                $uriPrefix . '&common=GET&get=GET'
            ],
            'POST' => [
                'POST',
                'Assigning addQueryStringMethod = POST is not supported anymore since TYPO3 v10.0',
                $uriPrefix
            ],
        ];
    }

    /**
     * @param string $method
     * @param string $expectedMessage
     * @param string $expectedResult
     *
     * @test
     * @dataProvider buildUriDataProvider
     */
    public function buildBackendUriHandlesRemovedMethods(string $method, string $expectedMessage, string $expectedResult): void
    {
        $_GET = ['common' => 'GET', 'get' => 'GET', 'route' => '/test/Path'];
        $this->expectException(Warning::class);
        $this->expectExceptionMessage($expectedMessage);
        $this->uriBuilder->setAddQueryStringMethod($method);
        static::assertSame($expectedResult, $this->uriBuilder->buildFrontendUri());
    }

    /**
     * Encodes square brackets in URL.
     *
     * @param string $string
     * @return string
     */
    private function rawUrlEncodeSquareBracketsInUrl($string)
    {
        return str_replace(['[', ']'], ['%5B', '%5D'], $string);
    }

    /**
     * @test
     */
    public function buildFrontendUriCreatesTypoLink()
    {
        /** @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $uriBuilder */
        $uriBuilder = $this->getAccessibleMock(UriBuilder::class, ['buildTypolinkConfiguration']);
        $uriBuilder->_set('contentObject', $this->mockContentObject);
        $uriBuilder->expects($this->once())->method('buildTypolinkConfiguration')->will($this->returnValue(['someTypoLinkConfiguration']));
        $this->mockContentObject->expects($this->once())->method('typoLink_URL')->with(['someTypoLinkConfiguration']);
        $uriBuilder->buildFrontendUri();
    }

    /**
     * @test
     */
    public function buildFrontendUriCreatesRelativeUrisByDefault()
    {
        $this->mockContentObject->expects($this->once())->method('typoLink_URL')->will($this->returnValue('relative/uri'));
        $expectedResult = 'relative/uri';
        $actualResult = $this->uriBuilder->buildFrontendUri();
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildFrontendUriDoesNotStripLeadingSlashesFromRelativeUris()
    {
        $this->mockContentObject->expects($this->once())->method('typoLink_URL')->will($this->returnValue('/relative/uri'));
        $expectedResult = '/relative/uri';
        $actualResult = $this->uriBuilder->buildFrontendUri();
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildFrontendUriCreatesAbsoluteUrisIfSpecified()
    {
        /** @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $uriBuilder */
        $uriBuilder = $this->getAccessibleMock(UriBuilder::class, ['buildTypolinkConfiguration']);
        $uriBuilder->_set('contentObject', $this->mockContentObject);
        $uriBuilder->expects($this->once())->method('buildTypolinkConfiguration')->will($this->returnValue(['foo' => 'bar']));
        $this->mockContentObject->expects($this->once())->method('typoLink_URL')->with(['foo' => 'bar', 'forceAbsoluteUrl' => true])->will($this->returnValue('http://baseuri/relative/uri'));
        $uriBuilder->setCreateAbsoluteUri(true);
        $expectedResult = 'http://baseuri/relative/uri';
        $actualResult = $uriBuilder->buildFrontendUri();
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildFrontendUriSetsAbsoluteUriSchemeIfSpecified()
    {
        /** @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $uriBuilder */
        $uriBuilder = $this->getAccessibleMock(UriBuilder::class, ['buildTypolinkConfiguration']);
        $uriBuilder->_set('contentObject', $this->mockContentObject);
        $uriBuilder->expects($this->once())->method('buildTypolinkConfiguration')->will($this->returnValue(['foo' => 'bar']));
        $this->mockContentObject->expects($this->once())->method('typoLink_URL')->with(['foo' => 'bar', 'forceAbsoluteUrl' => true, 'forceAbsoluteUrl.' => ['scheme' => 'someScheme']])->will($this->returnValue('http://baseuri/relative/uri'));
        $uriBuilder->setCreateAbsoluteUri(true);
        $uriBuilder->setAbsoluteUriScheme('someScheme');
        $expectedResult = 'http://baseuri/relative/uri';
        $actualResult = $uriBuilder->buildFrontendUri();
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildFrontendUriDoesNotSetAbsoluteUriSchemeIfCreateAbsoluteUriIsFalse()
    {
        /** @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $uriBuilder */
        $uriBuilder = $this->getAccessibleMock(UriBuilder::class, ['buildTypolinkConfiguration']);
        $uriBuilder->_set('contentObject', $this->mockContentObject);
        $uriBuilder->expects($this->once())->method('buildTypolinkConfiguration')->will($this->returnValue(['foo' => 'bar']));
        $this->mockContentObject->expects($this->once())->method('typoLink_URL')->with(['foo' => 'bar'])->will($this->returnValue('http://baseuri/relative/uri'));
        $uriBuilder->setCreateAbsoluteUri(false);
        $uriBuilder->setAbsoluteUriScheme('someScheme');
        $expectedResult = 'http://baseuri/relative/uri';
        $actualResult = $uriBuilder->buildFrontendUri();
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function resetSetsAllOptionsToTheirDefaultValue()
    {
        $this->uriBuilder
            ->setArguments(['test' => 'arguments'])
            ->setSection('testSection')
            ->setFormat('someFormat')
            ->setCreateAbsoluteUri(true)
            ->setAddQueryString(true)
            ->setAddQueryStringMethod('test')
            ->setArgumentsToBeExcludedFromQueryString(['test' => 'addQueryStringExcludeArguments'])
            ->setLinkAccessRestrictedPages(true)
            ->setTargetPageUid(123)
            ->setTargetPageType(321)
            ->setNoCache(true)
            ->setArgumentPrefix('testArgumentPrefix')
            ->setAbsoluteUriScheme('test')
        ;

        $this->uriBuilder->reset();
        $this->assertEquals([], $this->uriBuilder->getArguments());
        $this->assertEquals('', $this->uriBuilder->getSection());
        $this->assertEquals('', $this->uriBuilder->getFormat());
        $this->assertEquals(false, $this->uriBuilder->getCreateAbsoluteUri());
        $this->assertEquals(false, $this->uriBuilder->getAddQueryString());
        $this->assertEquals([], $this->uriBuilder->getArgumentsToBeExcludedFromQueryString());
        $this->assertEquals('', $this->uriBuilder->getAddQueryStringMethod());
        $this->assertEquals('', $this->uriBuilder->getArgumentPrefix());
        $this->assertEquals(false, $this->uriBuilder->getLinkAccessRestrictedPages());
        $this->assertEquals(null, $this->uriBuilder->getTargetPageUid());
        $this->assertEquals(0, $this->uriBuilder->getTargetPageType());
        $this->assertEquals(false, $this->uriBuilder->getNoCache());
        $this->assertEquals(false, $this->uriBuilder->getNoCache());
        $this->assertEquals(null, $this->uriBuilder->getAbsoluteUriScheme());
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationRespectsSpecifiedTargetPageUid()
    {
        $GLOBALS['TSFE']->id = 123;
        $this->uriBuilder->setTargetPageUid(321);
        $expectedConfiguration = ['parameter' => 321];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationUsesCurrentPageUidIfTargetPageUidIsNotSet()
    {
        $GLOBALS['TSFE']->id = 123;
        $expectedConfiguration = ['parameter' => 123];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationProperlySetsAdditionalArguments()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setArguments(['foo' => 'bar', 'baz' => ['extbase' => 'fluid']]);
        $expectedConfiguration = ['parameter' => 123, 'additionalParams' => '&foo=bar&baz%5Bextbase%5D=fluid'];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationProperlySetsAddQueryString()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setAddQueryString(true);
        $expectedConfiguration = ['parameter' => 123, 'addQueryString' => 1];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationProperlySetsAddQueryStringMethod()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setAddQueryString(true);
        $this->uriBuilder->setAddQueryStringMethod('GET');
        $expectedConfiguration = ['parameter' => 123, 'addQueryString' => 1, 'addQueryString.' => ['method' => 'GET']];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationConvertsDomainObjects()
    {
        $mockDomainObject1 = $this->getAccessibleMock(AbstractEntity::class, ['dummy']);
        $mockDomainObject1->_set('uid', '123');
        $mockDomainObject2 = $this->getAccessibleMock(AbstractEntity::class, ['dummy']);
        $mockDomainObject2->_set('uid', '321');
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setArguments(['someDomainObject' => $mockDomainObject1, 'baz' => ['someOtherDomainObject' => $mockDomainObject2]]);
        $expectedConfiguration = ['parameter' => 123, 'additionalParams' => '&someDomainObject=123&baz%5BsomeOtherDomainObject%5D=321'];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationResolvesPageTypeFromFormat()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setFormat('txt');
        $this->mockRequest->expects($this->once())->method('getControllerExtensionName')->will($this->returnValue('SomeExtensionNameFromRequest'));

        $mockConfigurationManager = $this->createMock(ConfigurationManager::class);
        $mockConfigurationManager->expects($this->any())->method('getConfiguration')
            ->will($this->returnValue(['formatToPageTypeMapping' => ['txt' => 2]]));
        $this->uriBuilder->_set('configurationManager', $mockConfigurationManager);

        $this->mockExtensionService->expects($this->any())->method('getTargetPageTypeByFormat')
            ->with('SomeExtensionNameFromRequest', 'txt')
            ->will($this->returnValue(2));

        $expectedConfiguration = ['parameter' => '123,2'];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationResolvesDefaultPageTypeFromFormatIfNoMappingIsConfigured()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setFormat('txt');

        $mockConfigurationManager = $this->createMock(ConfigurationManager::class);
        $mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue([]));
        $this->uriBuilder->_set('configurationManager', $mockConfigurationManager);

        $this->mockExtensionService->expects($this->any())->method('getTargetPageTypeByFormat')
            ->with(null, 'txt')
            ->will($this->returnValue(0));

        $expectedConfiguration = ['parameter' => '123,0'];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');

        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationResolvesDefaultPageTypeFromFormatIfFormatIsNotMapped()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setFormat('txt');

        $mockConfigurationManager = $this->createMock(ConfigurationManager::class);
        $mockConfigurationManager->expects($this->any())->method('getConfiguration')
            ->will($this->returnValue(['formatToPageTypeMapping' => ['pdf' => 2]]));
        $this->uriBuilder->_set('configurationManager', $mockConfigurationManager);

        $this->mockExtensionService->expects($this->any())->method('getTargetPageTypeByFormat')
            ->with(null, 'txt')
            ->will($this->returnValue(0));

        $expectedConfiguration = ['parameter' => '123,0'];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');

        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationDisablesCacheHashIfNoCacheIsSet()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setNoCache(true);
        $expectedConfiguration = ['parameter' => 123, 'no_cache' => 1];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationConsidersSection()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setSection('SomeSection');
        $expectedConfiguration = ['parameter' => 123, 'section' => 'SomeSection'];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function buildTypolinkConfigurationLinkAccessRestrictedPagesSetting()
    {
        $this->uriBuilder->setTargetPageUid(123);
        $this->uriBuilder->setLinkAccessRestrictedPages(true);
        $expectedConfiguration = ['parameter' => 123, 'linkAccessRestrictedPages' => 1];
        $actualConfiguration = $this->uriBuilder->_call('buildTypolinkConfiguration');
        $this->assertEquals($expectedConfiguration, $actualConfiguration);
    }

    /**
     * @test
     */
    public function convertDomainObjectsToIdentityArraysConvertsDomainObjects()
    {
        $mockDomainObject1 = $this->getAccessibleMock(AbstractEntity::class, ['dummy']);
        $mockDomainObject1->_set('uid', '123');
        $mockDomainObject2 = $this->getAccessibleMock(AbstractEntity::class, ['dummy']);
        $mockDomainObject2->_set('uid', '321');
        $expectedResult = ['foo' => ['bar' => 'baz'], 'domainObject1' => '123', 'second' => ['domainObject2' => '321']];
        $actualResult = $this->uriBuilder->_call('convertDomainObjectsToIdentityArrays', ['foo' => ['bar' => 'baz'], 'domainObject1' => $mockDomainObject1, 'second' => ['domainObject2' => $mockDomainObject2]]);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function convertDomainObjectsToIdentityArraysConvertsObjectStoragesWithDomainObjects()
    {
        $objectStorage  = new ObjectStorage();
        $mockChildObject1 = $this->getAccessibleMock(AbstractEntity::class, ['dummy']);
        $mockChildObject1->_set('uid', '123');
        $objectStorage->attach($mockChildObject1);
        $expectedResult = ['foo' => ['bar' => 'baz'], 'objectStorage' => ['123']];
        $actualResult = $this->uriBuilder->_call('convertDomainObjectsToIdentityArrays', ['foo' => ['bar' => 'baz'], 'objectStorage' => $objectStorage]);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function conversionOfTansientObjectsIsInvoked()
    {
        $mockValueObject = new ValueObjectFixture();
        $mockValueObject->name = 'foo';
        /** @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|object $mockUriBuilder */
        $mockUriBuilder = $this->getAccessibleMock(UriBuilder::class, ['convertTransientObjectToArray']);
        $mockUriBuilder->expects($this->once())->method('convertTransientObjectToArray')->will($this->returnValue(['foo' => 'bar']));
        $actualResult = $mockUriBuilder->_call('convertDomainObjectsToIdentityArrays', ['object' => $mockValueObject]);
        $expectedResult = ['object' => ['foo' => 'bar']];
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function conversionOfTansientObjectsThrowsExceptionForOtherThanValueObjects()
    {
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionCode(1260881688);
        $mockEntity = new EntityFixture();
        $mockEntity->name = 'foo';
        /** @var UriBuilder|\PHPUnit_Framework_MockObject_MockObject|object $mockUriBuilder */
        $mockUriBuilder = $this->getAccessibleMock(UriBuilder::class, ['dummy']);
        $mockUriBuilder->_call('convertDomainObjectsToIdentityArrays', ['object' => $mockEntity]);
    }

    /**
     * @test
     */
    public function tansientObjectsAreConvertedToAnArrayOfProperties()
    {
        $mockValueObject = new ValueObjectFixture();
        $mockValueObject->name = 'foo';
        $uriBuilder = new UriBuilder();
        $actualResult = $uriBuilder->convertTransientObjectToArray($mockValueObject);
        $expectedResult = ['name' => 'foo', 'object' => null, 'uid' => null, 'pid' => null];
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function tansientObjectsWithObjectStorageAreConvertedToAnArrayOfProperties()
    {
        $mockValueObject = new ValueObjectFixture();
        $objectStorage = new ObjectStorage();
        $mockValueObject->name = 'foo';
        $mockValueObject2 = new ValueObjectFixture();
        $mockValueObject2->name = 'bar';
        $objectStorage->attach($mockValueObject2);
        $mockValueObject->object = $objectStorage;
        $uriBuilder = new UriBuilder();
        $actualResult = $uriBuilder->convertTransientObjectToArray($mockValueObject);
        $expectedResult = [
            'name' => 'foo',
            'object' => [
                [
                    'name' => 'bar',
                    'uid' => null,
                    'pid' => null,
                    'object' => null
                ]
            ],
            'uid' => null,
            'pid' => null
        ];
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function tansientObjectsAreRecursivelyConverted()
    {
        $mockInnerValueObject2 = new ValueObjectFixture();
        $mockInnerValueObject2->name = 'foo';
        $mockInnerValueObject2->uid = 99;
        $mockInnerValueObject1 = new ValueObjectFixture();
        $mockInnerValueObject1->object = $mockInnerValueObject2;
        $mockValueObject = new ValueObjectFixture();
        $mockValueObject->object = $mockInnerValueObject1;
        $uriBuilder = new UriBuilder();
        $actualResult = $uriBuilder->convertTransientObjectToArray($mockValueObject);
        $expectedResult = [
            'name' => null,
            'object' => [
                'name' => null,
                'object' => 99,
                'uid' => null,
                'pid' => null
            ],
            'uid' => null,
            'pid' => null
        ];
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function removeDefaultControllerAndActionDoesNotModifyArgumentsifSpecifiedControlerAndActionIsNotEqualToDefaults()
    {
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultControllerNameByPlugin')->with('ExtensionName', 'PluginName')->will($this->returnValue('DefaultController'));
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultActionNameByPluginAndController')->with('ExtensionName', 'PluginName', 'SomeController')->will($this->returnValue('defaultAction'));
        $arguments = ['controller' => 'SomeController', 'action' => 'someAction', 'foo' => 'bar'];
        $extensionName = 'ExtensionName';
        $pluginName = 'PluginName';
        $expectedResult = ['controller' => 'SomeController', 'action' => 'someAction', 'foo' => 'bar'];
        $actualResult = $this->uriBuilder->_callRef('removeDefaultControllerAndAction', $arguments, $extensionName, $pluginName);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function removeDefaultControllerAndActionRemovesControllerIfItIsEqualToTheDefault()
    {
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultControllerNameByPlugin')->with('ExtensionName', 'PluginName')->will($this->returnValue('DefaultController'));
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultActionNameByPluginAndController')->with('ExtensionName', 'PluginName', 'DefaultController')->will($this->returnValue('defaultAction'));
        $arguments = ['controller' => 'DefaultController', 'action' => 'someAction', 'foo' => 'bar'];
        $extensionName = 'ExtensionName';
        $pluginName = 'PluginName';
        $expectedResult = ['action' => 'someAction', 'foo' => 'bar'];
        $actualResult = $this->uriBuilder->_callRef('removeDefaultControllerAndAction', $arguments, $extensionName, $pluginName);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function removeDefaultControllerAndActionRemovesActionIfItIsEqualToTheDefault()
    {
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultControllerNameByPlugin')->with('ExtensionName', 'PluginName')->will($this->returnValue('DefaultController'));
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultActionNameByPluginAndController')->with('ExtensionName', 'PluginName', 'SomeController')->will($this->returnValue('defaultAction'));
        $arguments = ['controller' => 'SomeController', 'action' => 'defaultAction', 'foo' => 'bar'];
        $extensionName = 'ExtensionName';
        $pluginName = 'PluginName';
        $expectedResult = ['controller' => 'SomeController', 'foo' => 'bar'];
        $actualResult = $this->uriBuilder->_callRef('removeDefaultControllerAndAction', $arguments, $extensionName, $pluginName);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function removeDefaultControllerAndActionRemovesControllerAndActionIfBothAreEqualToTheDefault()
    {
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultControllerNameByPlugin')->with('ExtensionName', 'PluginName')->will($this->returnValue('DefaultController'));
        $this->mockExtensionService->expects($this->atLeastOnce())->method('getDefaultActionNameByPluginAndController')->with('ExtensionName', 'PluginName', 'DefaultController')->will($this->returnValue('defaultAction'));
        $arguments = ['controller' => 'DefaultController', 'action' => 'defaultAction', 'foo' => 'bar'];
        $extensionName = 'ExtensionName';
        $pluginName = 'PluginName';
        $expectedResult = ['foo' => 'bar'];
        $actualResult = $this->uriBuilder->_callRef('removeDefaultControllerAndAction', $arguments, $extensionName, $pluginName);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @return array
     */
    public function convertIteratorToArrayConvertsIteratorsToArrayProvider()
    {
        return [
            'Extbase ObjectStorage' => [new ObjectStorage()],
            'SplObjectStorage' => [new \SplObjectStorage()],
            'ArrayIterator' => [new \ArrayIterator()]
        ];
    }

    /**
     * @dataProvider convertIteratorToArrayConvertsIteratorsToArrayProvider
     * @test
     */
    public function convertIteratorToArrayConvertsIteratorsToArray($iterator)
    {
        $result = $this->uriBuilder->_call('convertIteratorToArray', $iterator);
        $this->assertTrue(is_array($result));
    }
}
