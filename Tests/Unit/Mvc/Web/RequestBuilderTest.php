<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Extbase\Tests\Unit\Mvc\Web;

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
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Exception;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidActionNameException;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidControllerNameException;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\RequestBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class RequestBuilderTest extends UnitTestCase
{
    /**
     * @var RequestBuilder|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $requestBuilder;

    /**
     * @var ConfigurationManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockConfigurationManager;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var ObjectManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockObjectManager;

    /**
     * @var ExtensionService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockExtensionService;

    /**
     * @var EnvironmentService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockEnvironmentService;

    /**
     * @var Request|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockRequest;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->requestBuilder = $this->getAccessibleMock(RequestBuilder::class, ['dummy']);
        $this->configuration = [
            'userFunc' => 'Tx_Extbase_Dispatcher->dispatch',
            'pluginName' => 'Pi1',
            'extensionName' => 'MyExtension',
            'controller' => 'TheFirstController',
            'action' => 'show',
            'controllerConfiguration' => [
                'MyExtension\Controller\TheFirstControllerController' => [
                    'className' => 'MyExtension\Controller\TheFirstControllerController',
                    'alias' => 'TheFirstController',
                    'actions' => ['show', 'index', 'new', 'create', 'delete', 'edit', 'update', 'setup', 'test']
                ],
                'MyExtension\Controller\TheSecondControllerController' => [
                    'className' => 'MyExtension\Controller\TheSecondControllerController',
                    'alias' => 'TheSecondController',
                    'actions' => ['show', 'index']
                ],
                'MyExtension\Controller\TheThirdControllerController' => [
                    'className' => 'MyExtension\Controller\TheThirdControllerController',
                    'alias' => 'TheThirdController',
                    'actions' => ['delete', 'create', 'onlyInThirdController']
                ]
            ]
        ];
        $this->mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $this->mockRequest = $this->createMock(Request::class);
        $this->mockObjectManager = $this->createMock(ObjectManagerInterface::class);
        $this->mockExtensionService = $this->createMock(ExtensionService::class);
        $this->mockEnvironmentService = $this->getMockBuilder(EnvironmentService::class)
            ->setMethods(['getServerRequestMethod', 'isEnvironmentInFrontendMode', 'isEnvironmentInBackendMode'])
            ->getMock();
        $this->mockEnvironmentService->expects($this->any())->method('isEnvironmentInFrontendMode')->willReturn(true);
        $this->mockEnvironmentService->expects($this->any())->method('isEnvironmentInBackendMode')->willReturn(false);
    }

    protected function injectDependencies(): void
    {
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockObjectManager->expects($this->any())->method('get')->with(Request::class)->will($this->returnValue($this->mockRequest));
        $this->requestBuilder->_set('objectManager', $this->mockObjectManager);
        $pluginNamespace = 'tx_' . strtolower($this->configuration['extensionName'] . '_' . $this->configuration['pluginName']);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue($pluginNamespace));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->mockEnvironmentService->expects($this->any())->method('getServerRequestMethod')->will($this->returnValue('GET'));
        $this->requestBuilder->_set('environmentService', $this->mockEnvironmentService);
    }

    /**
     * @test
     */
    public function buildReturnsAWebRequestObject(): void
    {
        $this->injectDependencies();
        $request = $this->requestBuilder->build();
        $this->assertSame($this->mockRequest, $request);
    }

    /**
     * @test
     */
    public function buildSetsRequestPluginName(): void
    {
        $this->injectDependencies();
        $this->mockRequest->expects($this->once())->method('setPluginName')->with('Pi1');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsRequestControllerExtensionName(): void
    {
        $this->injectDependencies();
        $this->mockRequest->expects($this->once())->method('setControllerExtensionName')->with('MyExtension');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsRequestControllerName(): void
    {
        $this->injectDependencies();
        $this->mockRequest->expects($this->once())->method('setControllerName')->with('TheFirstController');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsRequestControllerActionName(): void
    {
        $this->injectDependencies();
        $this->mockRequest->expects($this->once())->method('setControllerActionName')->with('show');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsRequestRequestUri(): void
    {
        $this->injectDependencies();
        $expectedRequestUri = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        $this->mockRequest->expects($this->once())->method('setRequestUri')->with($expectedRequestUri);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsRequestBaseUri(): void
    {
        $this->injectDependencies();
        $expectedBaseUri = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        $this->mockRequest->expects($this->once())->method('setBaseUri')->with($expectedBaseUri);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsRequestMethod(): void
    {
        $this->injectDependencies();
        $expectedMethod = 'SomeRequestMethod';
        $mockEnvironmentService = $this->getMockBuilder(EnvironmentService::class)
            ->setMethods(['getServerRequestMethod'])
            ->getMock();
        $mockEnvironmentService->expects($this->once())->method('getServerRequestMethod')->will($this->returnValue($expectedMethod));
        $this->requestBuilder->_set('environmentService', $mockEnvironmentService);
        $this->mockRequest->expects($this->once())->method('setMethod')->with($expectedMethod);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfExtensionNameIsNotConfigured(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1289843275);
        unset($this->configuration['extensionName']);
        $mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $mockConfigurationManager);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfPluginNameIsNotConfigured(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1289843277);
        unset($this->configuration['pluginName']);
        $mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $mockConfigurationManager);
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfControllerConfigurationIsEmptyOrNotSet(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1316104317);
        $this->configuration['controllerConfiguration'] = [];
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfControllerConfigurationHasNoDefaultActionDefined(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1295479651);
        $this->configuration['controllerConfiguration']['MyExtension\Controller\TheFirstControllerController'] = [
            'className' => 'MyExtension\Controller\TheFirstControllerController',
            'alias' => 'TheFirstController',
        ];
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfNoDefaultControllerCanBeResolved(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1316104317);
        $this->configuration['controllerConfiguration'] = [
            '' => [
                'className' => '',
                'alias' => '',
                'actions' => ['foo']
            ]
        ];
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsParametersFromGetAndPostVariables(): void
    {
        $this->configuration['extensionName'] = 'SomeExtensionName';
        $this->configuration['pluginName'] = 'SomePluginName';
        $this->injectDependencies();
        $_GET = [
            'tx_someotherextensionname_somepluginname' => [
                'foo' => 'bar'
            ],
            'tx_someextensionname_somepluginname' => [
                'parameter1' => 'valueGetsOverwritten',
                'parameter2' => [
                    'parameter3' => 'value3'
                ]
            ]
        ];
        $_POST = [
            'tx_someextensionname_someotherpluginname' => [
                'foo' => 'bar'
            ],
            'tx_someextensionname_somepluginname' => [
                'parameter1' => 'value1',
                'parameter2' => [
                    'parameter4' => 'value4'
                ]
            ]
        ];

        // testing at which position something gets called is fishy.
        // why not make this a functional test and test with an actual requestBuilder instance and check the arguments
        // later on?
        $this->mockRequest->expects($this->at(9))->method('setArgument')->with('parameter1', 'value1');
        $this->mockRequest->expects($this->at(10))->method('setArgument')->with(
            'parameter2',
            ['parameter3' => 'value3', 'parameter4' => 'value4']
        );
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsFormatFromGetAndPostVariables(): void
    {
        $this->configuration['extensionName'] = 'SomeExtensionName';
        $this->configuration['pluginName'] = 'SomePluginName';
        $this->injectDependencies();
        $_GET = [
            'tx_someextensionname_somepluginname' => [
                'format' => 'GET'
            ]
        ];
        $_POST = [
            'tx_someextensionname_somepluginname' => [
                'format' => 'POST'
            ]
        ];
        // phew! Shitty position tests. Who thought this was a good idea?
        $this->mockRequest->expects($this->at(8))->method('setFormat')->with('POST');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildCorrectlySetsAllowedControllerActions(): void
    {
        $this->injectDependencies();
        $expectedResult = [
            'MyExtension\Controller\TheFirstControllerController' => [
                'show',
                'index',
                'new',
                'create',
                'delete',
                'edit',
                'update',
                'setup',
                'test'
            ],
            'MyExtension\Controller\TheSecondControllerController' => [
                'show',
                'index'
            ],
            'MyExtension\Controller\TheThirdControllerController' => [
                'delete',
                'create',
                'onlyInThirdController'
            ]
        ];
        $this->requestBuilder->build();
        $actualResult = $this->requestBuilder->_get('allowedControllerActions');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfDefaultControllerCantBeDetermined(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1316104317);
        $this->configuration['controllerConfiguration'] = [];
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_myextension_pi1'));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsDefaultControllerIfNoControllerIsSpecified(): void
    {
        $this->injectDependencies();
        $_GET = [
            'tx_myextension_pi1' => [
                'foo' => 'bar'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerName')->with('TheFirstController');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildCorrectlySetsSpecifiedControllerNameIfItsAllowedForTheCurrentPlugin(): void
    {
        $this->injectDependencies();
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'TheSecondController'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerName')->with('TheSecondController');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsInvalidControllerNameExceptionIfSpecifiedControllerIsNotAllowed(): void
    {
        $this->expectException(InvalidControllerNameException::class);
        $this->expectExceptionCode(1313855173);
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_myextension_pi1'));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'SomeInvalidController'
            ]
        ];
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsPageNotFoundExceptionIfEnabledAndSpecifiedControllerIsNotAllowed(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(1313857897);
        $this->configuration['mvc']['throwPageNotFoundExceptionIfActionCantBeResolved'] = 1;
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_myextension_pi1'));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'SomeInvalidController'
            ]
        ];
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsDefaultControllerNameIfSpecifiedControllerIsNotAllowedAndCallDefaultActionIfActionCantBeResolvedIsSet(): void
    {
        $this->configuration['mvc']['callDefaultActionIfActionCantBeResolved'] = 1;
        $this->injectDependencies();
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'SomeInvalidController'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerName')->with('TheFirstController');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsExceptionIfDefaultActionCantBeDetermined(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1316104317);
        $this->configuration['controllerConfiguration'] = [];
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_myextension_pi1'));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsDefaultActionOfTheCurrentControllerIfNoActionIsSpecified(): void
    {
        $this->injectDependencies();
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'TheThirdController'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerActionName')->with('delete');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildCorrectlySetsSpecifiedActionNameForTheDefaultControllerIfItsAllowedForTheCurrentPlugin(): void
    {
        $this->injectDependencies();
        $_GET = [
            'tx_myextension_pi1' => [
                'action' => 'create'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerActionName')->with('create');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildCorrectlySetsSpecifiedActionNameForTheSpecifiedControllerIfItsAllowedForTheCurrentPlugin(): void
    {
        $this->injectDependencies();
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'TheThirdController',
                'action' => 'onlyInThirdController'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerActionName')->with('onlyInThirdController');
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsInvalidActionNameExceptionIfSpecifiedActionIsNotAllowed(): void
    {
        $this->expectException(InvalidActionNameException::class);
        $this->expectExceptionCode(1313855175);
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_myextension_pi1'));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $_GET = [
            'tx_myextension_pi1' => [
                'action' => 'someInvalidAction'
            ]
        ];
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildThrowsPageNotFoundExceptionIfEnabledAndSpecifiedActionIsNotAllowed(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(1313857898);
        $this->configuration['mvc']['throwPageNotFoundExceptionIfActionCantBeResolved'] = 1;
        $this->mockConfigurationManager->expects($this->any())->method('getConfiguration')->will($this->returnValue($this->configuration));
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
        $this->mockExtensionService->expects($this->any())->method('getPluginNamespace')->will($this->returnValue('tx_myextension_pi1'));
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $_GET = [
            'tx_myextension_pi1' => [
                'action' => 'someInvalidAction'
            ]
        ];
        $this->requestBuilder->build();
    }

    /**
     * @test
     */
    public function buildSetsDefaultActionNameIfSpecifiedActionIsNotAllowedAndCallDefaultActionIfActionCantBeResolvedIsSet(): void
    {
        $this->configuration['mvc']['callDefaultActionIfActionCantBeResolved'] = 1;
        $this->injectDependencies();
        $this->requestBuilder->_set('extensionService', $this->mockExtensionService);
        $_GET = [
            'tx_myextension_pi1' => [
                'controller' => 'TheThirdController',
                'action' => 'someInvalidAction'
            ]
        ];
        $this->mockRequest->expects($this->once())->method('setControllerName')->with('TheThirdController');
        $this->mockRequest->expects($this->once())->method('setControllerActionName')->with('delete');
        $this->requestBuilder->build();
    }

    /**
     * @test
     * @see TYPO3\Flow\Tests\Unit\Utility\EnvironmentTest
     */
    public function untangleFilesArrayTransformsTheFilesSuperglobalIntoAManageableForm(): void
    {
        $convolutedFiles = [
            'a0' => [
                'name' => [
                    'a1' => 'a.txt'
                ],
                'type' => [
                    'a1' => 'text/plain'
                ],
                'tmp_name' => [
                    'a1' => '/private/var/tmp/phpbqXsYt'
                ],
                'error' => [
                    'a1' => 0
                ],
                'size' => [
                    'a1' => 100
                ]
            ],
            'b0' => [
                'name' => [
                    'b1' => 'b.txt'
                ],
                'type' => [
                    'b1' => 'text/plain'
                ],
                'tmp_name' => [
                    'b1' => '/private/var/tmp/phpvZ6oUD'
                ],
                'error' => [
                    'b1' => 0
                ],
                'size' => [
                    'b1' => 200
                ]
            ],
            'c' => [
                'name' => 'c.txt',
                'type' => 'text/plain',
                'tmp_name' => '/private/var/tmp/phpS9KMNw',
                'error' => 0,
                'size' => 300
            ],
            'd0' => [
                'name' => [
                    'd1' => [
                        0 => 'd12.txt',
                        'd2' => [
                            'd3' => 'd.txt'
                        ]
                    ]
                ],
                'type' => [
                    'd1' => [
                        0 => 'text/plain',
                        'd2' => [
                            'd3' => 'text/plain'
                        ]
                    ]
                ],
                'tmp_name' => [
                    'd1' => [
                        0 => '/private/var/tmp/phpMf9Qx9',
                        'd2' => [
                            'd3' => '/private/var/tmp/phprR3fax'
                        ]
                    ]
                ],
                'error' => [
                    'd1' => [
                        0 => 0,
                        'd2' => [
                            'd3' => 0
                        ]
                    ]
                ],
                'size' => [
                    'd1' => [
                        0 => 200,
                        'd2' => [
                            'd3' => 400
                        ]
                    ]
                ]
            ],
            'e0' => [
                'name' => [
                    'e1' => [
                        'e2' => [
                            0 => 'e_one.txt',
                            1 => 'e_two.txt'
                        ]
                    ]
                ],
                'type' => [
                    'e1' => [
                        'e2' => [
                            0 => 'text/plain',
                            1 => 'text/plain'
                        ]
                    ]
                ],
                'tmp_name' => [
                    'e1' => [
                        'e2' => [
                            0 => '/private/var/tmp/php01fitB',
                            1 => '/private/var/tmp/phpUUB2cv'
                        ]
                    ]
                ],
                'error' => [
                    'e1' => [
                        'e2' => [
                            0 => 0,
                            1 => 0
                        ]
                    ]
                ],
                'size' => [
                    'e1' => [
                        'e2' => [
                            0 => 510,
                            1 => 520
                        ]
                    ]
                ]
            ],
            'error' => [
                'name' => 'error_file.txt',
                'type' => 'text/plain',
                'tmp_name' => '/private/var/tmp/phpADDu87fE',
                'error' => 0,
                'size' => 120
            ]
        ];
        $untangledFiles = [
            'a0' => [
                'a1' => [
                    'name' => 'a.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/private/var/tmp/phpbqXsYt',
                    'error' => 0,
                    'size' => 100
                ]
            ],
            'b0' => [
                'b1' => [
                    'name' => 'b.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/private/var/tmp/phpvZ6oUD',
                    'error' => 0,
                    'size' => 200
                ]
            ],
            'c' => [
                'name' => 'c.txt',
                'type' => 'text/plain',
                'tmp_name' => '/private/var/tmp/phpS9KMNw',
                'error' => 0,
                'size' => 300
            ],
            'd0' => [
                'd1' => [
                    0 => [
                        'name' => 'd12.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/private/var/tmp/phpMf9Qx9',
                        'error' => 0,
                        'size' => 200
                    ],
                    'd2' => [
                        'd3' => [
                            'name' => 'd.txt',
                            'type' => 'text/plain',
                            'tmp_name' => '/private/var/tmp/phprR3fax',
                            'error' => 0,
                            'size' => 400
                        ]
                    ]
                ]
            ],
            'e0' => [
                'e1' => [
                    'e2' => [
                        0 => [
                            'name' => 'e_one.txt',
                            'type' => 'text/plain',
                            'tmp_name' => '/private/var/tmp/php01fitB',
                            'error' => 0,
                            'size' => 510
                        ],
                        1 => [
                            'name' => 'e_two.txt',
                            'type' => 'text/plain',
                            'tmp_name' => '/private/var/tmp/phpUUB2cv',
                            'error' => 0,
                            'size' => 520
                        ]
                    ]
                ]
            ],
            'error' => [
                'name' => 'error_file.txt',
                'type' => 'text/plain',
                'tmp_name' => '/private/var/tmp/phpADDu87fE',
                'error' => 0,
                'size' => 120
            ]
        ];
        $requestBuilder = $this->getAccessibleMock(RequestBuilder::class, ['dummy'], [], '', false);
        $result = $requestBuilder->_call('untangleFilesArray', $convolutedFiles);
        $this->assertSame($untangledFiles, $result);
    }
}
