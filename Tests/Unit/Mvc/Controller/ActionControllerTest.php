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

namespace TYPO3\CMS\Extbase\Tests\Unit\Mvc\Controller;

use Prophecy\Argument;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentTypeException;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchActionException;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Reflection\ClassSchema;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Method;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\View\AbstractTemplateView;
use TYPO3Fluid\Fluid\View\TemplateView as FluidTemplateView;

/**
 * Test case
 */
class ActionControllerTest extends UnitTestCase
{
    /**
     * @var bool Reset singletons created by subject
     */
    protected $resetSingletonInstances = true;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Controller\ActionController|\PHPUnit\Framework\MockObject\MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $actionController;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $mockObjectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder
     */
    protected $mockUriBuilder;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService
     */
    protected $mockMvcPropertyMappingConfigurationService;

    /**
     * @test
     */
    public function resolveActionMethodNameReturnsTheCurrentActionMethodNameFromTheRequest()
    {
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->expects(self::once())->method('getControllerActionName')->willReturn('fooBar');
        /** @var \TYPO3\CMS\Extbase\Mvc\Controller\ActionController|\PHPUnit\Framework\MockObject\MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface */
        $mockController = $this->getAccessibleMock(ActionController::class, ['fooBarAction'], [], '', false);
        $mockController->_set('request', $mockRequest);
        self::assertEquals('fooBarAction', $mockController->_call('resolveActionMethodName'));
    }

    /**
     * @test
     */
    public function resolveActionMethodNameThrowsAnExceptionIfTheActionDefinedInTheRequestDoesNotExist()
    {
        $this->expectException(NoSuchActionException::class);
        $this->expectExceptionCode(1186669086);
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->expects(self::once())->method('getControllerActionName')->willReturn('fooBar');
        /** @var \TYPO3\CMS\Extbase\Mvc\Controller\ActionController|\PHPUnit\Framework\MockObject\MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface */
        $mockController = $this->getAccessibleMock(ActionController::class, ['otherBarAction'], [], '', false);
        $mockController->_set('request', $mockRequest);
        $mockController->_call('resolveActionMethodName');
    }

    /**
     * @test
     *
     * @todo: make this a functional test
     */
    public function initializeActionMethodArgumentsRegistersArgumentsFoundInTheSignatureOfTheCurrentActionMethod(): void
    {
        $mockRequest = $this->createMock(Request::class);
        $mockArguments = $this->getMockBuilder(Arguments::class)
            ->setMethods(['addNewArgument', 'removeAll'])
            ->getMock();
        $mockArguments->expects(self::at(0))->method('addNewArgument')->with('stringArgument', 'string', true);
        $mockArguments->expects(self::at(1))->method('addNewArgument')->with('integerArgument', 'integer', true);
        $mockArguments->expects(self::at(2))->method('addNewArgument')->with('objectArgument', 'F3_Foo_Bar', true);
        $mockController = $this->getAccessibleMock(ActionController::class, ['fooAction', 'evaluateDontValidateAnnotations'], [], '', false);

        $classSchemaMethod = new Method(
            'fooAction',
            [
                'params' => [
                    'stringArgument' => [
                        'position' => 0,
                        'byReference' => false,
                        'array' => false,
                        'optional' => false,
                        'allowsNull' => false,
                        'type' => 'string',
                        'hasDefaultValue' => false
                    ],
                    'integerArgument' => [
                        'position' => 1,
                        'byReference' => false,
                        'array' => false,
                        'optional' => false,
                        'allowsNull' => false,
                        'type' => 'integer',
                        'hasDefaultValue' => false
                    ],
                    'objectArgument' => [
                        'position' => 2,
                        'byReference' => false,
                        'array' => false,
                        'optional' => false,
                        'allowsNull' => false,
                        'type' => 'F3_Foo_Bar',
                        'hasDefaultValue' => false
                    ]
                ]
            ],
            get_class($mockController)
        );

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock
            ->expects(self::any())
            ->method('getMethod')
            ->with('fooAction')
            ->willReturn($classSchemaMethod);

        $mockReflectionService = $this->createMock(ReflectionService::class);
        $mockReflectionService
            ->expects(self::any())
            ->method('getClassSchema')
            ->with(get_class($mockController))
            ->willReturn($classSchemaMock);
        $mockController->_set('reflectionService', $mockReflectionService);
        $mockController->_set('request', $mockRequest);
        $mockController->_set('arguments', $mockArguments);
        $mockController->_set('actionMethodName', 'fooAction');
        $mockController->_call('initializeActionMethodArguments');
    }

    /**
     * @test
     */
    public function initializeActionMethodArgumentsRegistersOptionalArgumentsAsSuch(): void
    {
        $mockRequest = $this->createMock(Request::class);
        $mockArguments = $this->createMock(Arguments::class);
        $mockArguments->expects(self::at(0))->method('addNewArgument')->with('arg1', 'string', true);
        $mockArguments->expects(self::at(1))->method('addNewArgument')->with('arg2', 'array', false, [21]);
        $mockArguments->expects(self::at(2))->method('addNewArgument')->with('arg3', 'string', false, 42);
        $mockController = $this->getAccessibleMock(ActionController::class, ['fooAction', 'evaluateDontValidateAnnotations'], [], '', false);

        $classSchemaMethod = new Method(
            'fooAction',
            [
                'params' => [
                    'arg1' => [
                        'position' => 0,
                        'byReference' => false,
                        'array' => false,
                        'optional' => false,
                        'allowsNull' => false,
                        'type' => 'string',
                        'hasDefaultValue' => false
                    ],
                    'arg2' => [
                        'position' => 1,
                        'byReference' => false,
                        'array' => true,
                        'optional' => true,
                        'defaultValue' => [21],
                        'allowsNull' => false,
                        'hasDefaultValue' => true
                    ],
                    'arg3' => [
                        'position' => 2,
                        'byReference' => false,
                        'array' => false,
                        'optional' => true,
                        'defaultValue' => 42,
                        'allowsNull' => false,
                        'type' => 'string',
                        'hasDefaultValue' => true
                    ]
                ]
            ],
            get_class($mockController)
        );

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock
            ->expects(self::any())
            ->method('getMethod')
            ->with('fooAction')
            ->willReturn($classSchemaMethod);

        $mockReflectionService = $this->createMock(ReflectionService::class);
        $mockReflectionService
            ->expects(self::any())
            ->method('getClassSchema')
            ->with(get_class($mockController))
            ->willReturn($classSchemaMock);
        $mockController->_set('reflectionService', $mockReflectionService);
        $mockController->_set('request', $mockRequest);
        $mockController->_set('arguments', $mockArguments);
        $mockController->_set('actionMethodName', 'fooAction');
        $mockController->_call('initializeActionMethodArguments');
    }

    /**
     * @test
     */
    public function initializeActionMethodArgumentsThrowsExceptionIfDataTypeWasNotSpecified(): void
    {
        $this->expectException(InvalidArgumentTypeException::class);
        $this->expectExceptionCode(1253175643);
        $mockRequest = $this->createMock(Request::class);
        $mockArguments = $this->createMock(Arguments::class);
        $mockController = $this->getAccessibleMock(ActionController::class, ['fooAction'], [], '', false);

        $classSchemaMethod = new Method(
            'fooAction',
            [
                'params' => [
                    'arg1' => [
                        'position' => 0,
                        'byReference' => false,
                        'array' => false,
                        'optional' => false,
                        'allowsNull' => false
                    ]
                ]
            ],
            get_class($mockController)
        );

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock
            ->expects(self::any())
            ->method('getMethod')
            ->with('fooAction')
            ->willReturn($classSchemaMethod);

        $mockReflectionService = $this->createMock(ReflectionService::class);
        $mockReflectionService
            ->expects(self::any())
            ->method('getClassSchema')
            ->with(get_class($mockController))
            ->willReturn($classSchemaMock);
        $mockController->_set('reflectionService', $mockReflectionService);
        $mockController->_set('request', $mockRequest);
        $mockController->_set('arguments', $mockArguments);
        $mockController->_set('actionMethodName', 'fooAction');
        $mockController->_call('initializeActionMethodArguments');
    }

    /**
     * @test
     * @dataProvider templateRootPathDataProvider
     * @param array $configuration
     * @param array $expected
     */
    public function setViewConfigurationResolvesTemplateRootPathsForTemplateRootPath($configuration, $expected)
    {
        /** @var ActionController|\PHPUnit\Framework\MockObject\MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $mockController */
        $mockController = $this->getAccessibleMock(ActionController::class, ['dummy'], [], '', false);
        /** @var ConfigurationManagerInterface|\PHPUnit\Framework\MockObject\MockObject $mockConfigurationManager */
        $mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $mockConfigurationManager->expects(self::any())->method('getConfiguration')->willReturn($configuration);
        $mockController->injectConfigurationManager($mockConfigurationManager);
        $mockController->_set('request', $this->createMock(Request::class), ['getControllerExtensionKey']);
        $view = $this->getMockBuilder(ViewInterface::class)
            ->setMethods(['setControllerContext', 'assign', 'assignMultiple', 'canRender', 'render', 'initializeView', 'setTemplateRootPaths'])
            ->getMock();
        $view->expects(self::once())->method('setTemplateRootPaths')->with($expected);
        $mockController->_call('setViewConfiguration', $view);
    }

    /**
     * @return array
     */
    public function templateRootPathDataProvider()
    {
        return [
            'text keys' => [
                [
                    'view' => [
                        'templateRootPaths' => [
                            'default' => 'some path',
                            'extended' => 'some other path'
                        ]
                    ]
                ],
                [
                    'extended' => 'some other path',
                    'default' => 'some path'
                ]
            ],
            'numerical keys' => [
                [
                    'view' => [
                        'templateRootPaths' => [
                            '10' => 'some path',
                            '20' => 'some other path',
                            '15' => 'intermediate specific path'
                        ]
                    ]
                ],
                [
                    '20' => 'some other path',
                    '15' => 'intermediate specific path',
                    '10' => 'some path'
                ]
            ],
            'mixed keys' => [
                [
                    'view' => [
                        'templateRootPaths' => [
                            '10' => 'some path',
                            'very_specific' => 'some other path',
                            '15' => 'intermediate specific path'
                        ]
                    ]
                ],
                [
                    '15' => 'intermediate specific path',
                    'very_specific' => 'some other path',
                    '10' => 'some path'
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider layoutRootPathDataProvider
     *
     * @param array $configuration
     * @param array $expected
     */
    public function setViewConfigurationResolvesLayoutRootPathsForLayoutRootPath($configuration, $expected)
    {
        /** @var ActionController|\PHPUnit\Framework\MockObject\MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $mockController */
        $mockController = $this->getAccessibleMock(ActionController::class, ['dummy'], [], '', false);
        /** @var ConfigurationManagerInterface|\PHPUnit\Framework\MockObject\MockObject $mockConfigurationManager */
        $mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $mockConfigurationManager->expects(self::any())->method('getConfiguration')->willReturn($configuration);
        $mockController->injectConfigurationManager($mockConfigurationManager);
        $mockController->_set('request', $this->createMock(Request::class), ['getControllerExtensionKey']);
        $view = $this->getMockBuilder(ViewInterface::class)
            ->setMethods(['setControllerContext', 'assign', 'assignMultiple', 'canRender', 'render', 'initializeView', 'setlayoutRootPaths'])
            ->getMock();
        $view->expects(self::once())->method('setlayoutRootPaths')->with($expected);
        $mockController->_call('setViewConfiguration', $view);
    }

    /**
     * @return array
     */
    public function layoutRootPathDataProvider()
    {
        return [
            'text keys' => [
                [
                    'view' => [
                        'layoutRootPaths' => [
                            'default' => 'some path',
                            'extended' => 'some other path'
                        ]
                    ]
                ],
                [
                    'extended' => 'some other path',
                    'default' => 'some path'
                ]
            ],
            'numerical keys' => [
                [
                    'view' => [
                        'layoutRootPaths' => [
                            '10' => 'some path',
                            '20' => 'some other path',
                            '15' => 'intermediate specific path'
                        ]
                    ]
                ],
                [
                    '20' => 'some other path',
                    '15' => 'intermediate specific path',
                    '10' => 'some path'
                ]
            ],
            'mixed keys' => [
                [
                    'view' => [
                        'layoutRootPaths' => [
                            '10' => 'some path',
                            'very_specific' => 'some other path',
                            '15' => 'intermediate specific path'
                        ]
                    ]
                ],
                [
                    '15' => 'intermediate specific path',
                    'very_specific' => 'some other path',
                    '10' => 'some path'
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider partialRootPathDataProvider
     *
     * @param array $configuration
     * @param array $expected
     */
    public function setViewConfigurationResolvesPartialRootPathsForPartialRootPath($configuration, $expected)
    {
        /** @var ActionController|\PHPUnit\Framework\MockObject\MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface $mockController */
        $mockController = $this->getAccessibleMock(ActionController::class, ['dummy'], [], '', false);
        /** @var ConfigurationManagerInterface|\PHPUnit\Framework\MockObject\MockObject $mockConfigurationManager */
        $mockConfigurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $mockConfigurationManager->expects(self::any())->method('getConfiguration')->willReturn($configuration);
        $mockController->injectConfigurationManager($mockConfigurationManager);
        $mockController->_set('request', $this->createMock(Request::class), ['getControllerExtensionKey']);
        $view = $this->getMockBuilder(ViewInterface::class)
            ->setMethods(['setControllerContext', 'assign', 'assignMultiple', 'canRender', 'render', 'initializeView', 'setpartialRootPaths'])
            ->getMock();
        $view->expects(self::once())->method('setpartialRootPaths')->with($expected);
        $mockController->_call('setViewConfiguration', $view);
    }

    /**
     * @return array
     */
    public function partialRootPathDataProvider()
    {
        return [
            'text keys' => [
                [
                    'view' => [
                        'partialRootPaths' => [
                            'default' => 'some path',
                            'extended' => 'some other path'
                        ]
                    ]
                ],
                [
                    'extended' => 'some other path',
                    'default' => 'some path'
                ]
            ],
            'numerical keys' => [
                [
                    'view' => [
                        'partialRootPaths' => [
                            '10' => 'some path',
                            '20' => 'some other path',
                            '15' => 'intermediate specific path'
                        ]
                    ]
                ],
                [
                    '20' => 'some other path',
                    '15' => 'intermediate specific path',
                    '10' => 'some path'
                ]
            ],
            'mixed keys' => [
                [
                    'view' => [
                        'partialRootPaths' => [
                            '10' => 'some path',
                            'very_specific' => 'some other path',
                            '15' => 'intermediate specific path'
                        ]
                    ]
                ],
                [
                    '15' => 'intermediate specific path',
                    'very_specific' => 'some other path',
                    '10' => 'some path'
                ]
            ],
        ];
    }

    /**
     * @param FluidTemplateView $viewMock
     * @param string|null $expectedHeader
     * @param string|null $expectedFooter
     * @test
     * @dataProvider headerAssetDataProvider
     */
    public function rendersAndAssignsAssetsFromViewIntoPageRenderer($viewMock, $expectedHeader, $expectedFooter)
    {
        $pageRenderer = $this->prophesize(PageRenderer::class);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRenderer->reveal());
        if (!empty(trim($expectedHeader ?? ''))) {
            $pageRenderer->addHeaderData($expectedHeader)->shouldBeCalled();
        } else {
            $pageRenderer->addHeaderData(Argument::any())->shouldNotBeCalled();
        }
        if (!empty(trim($expectedFooter ?? ''))) {
            $pageRenderer->addFooterData($expectedFooter)->shouldBeCalled();
        } else {
            $pageRenderer->addFooterData(Argument::any())->shouldNotBeCalled();
        }
        $requestMock = $this->getMockBuilder(RequestInterface::class)->getMockForAbstractClass();
        $subject = new ActionController('');
        $viewProperty = new \ReflectionProperty($subject, 'view');
        $viewProperty->setAccessible(true);
        $viewProperty->setValue($subject, $viewMock);

        $method = new \ReflectionMethod($subject, 'renderAssetsForRequest');
        $method->setAccessible(true);
        $method->invokeArgs($subject, [$requestMock]);
    }

    /**
     * @return array
     */
    public function headerAssetDataProvider()
    {
        $viewWithHeaderData = $this->getMockBuilder(FluidTemplateView::class)->setMethods(['renderSection'])->disableOriginalConstructor()->getMock();
        $viewWithHeaderData->expects(self::at(0))->method('renderSection')->with('HeaderAssets', self::anything(), true)->willReturn('custom-header-data');
        $viewWithHeaderData->expects(self::at(1))->method('renderSection')->with('FooterAssets', self::anything(), true)->willReturn(null);
        $viewWithFooterData = $this->getMockBuilder(FluidTemplateView::class)->setMethods(['renderSection'])->disableOriginalConstructor()->getMock();
        $viewWithFooterData->expects(self::at(0))->method('renderSection')->with('HeaderAssets', self::anything(), true)->willReturn(null);
        $viewWithFooterData->expects(self::at(1))->method('renderSection')->with('FooterAssets', self::anything(), true)->willReturn('custom-footer-data');
        $viewWithBothData = $this->getMockBuilder(FluidTemplateView::class)->setMethods(['renderSection'])->disableOriginalConstructor()->getMock();
        $viewWithBothData->expects(self::at(0))->method('renderSection')->with('HeaderAssets', self::anything(), true)->willReturn('custom-header-data');
        $viewWithBothData->expects(self::at(1))->method('renderSection')->with('FooterAssets', self::anything(), true)->willReturn('custom-footer-data');
        $invalidView = $this->getMockBuilder(AbstractTemplateView::class)->disableOriginalConstructor()->getMockForAbstractClass();
        return [
            [$viewWithHeaderData, 'custom-header-data', null],
            [$viewWithFooterData, null, 'custom-footer-data'],
            [$viewWithBothData, 'custom-header-data', 'custom-footer-data'],
            [$invalidView, null, null]
        ];
    }

    /**
     * @return array
     */
    public function addFlashMessageDataProvider()
    {
        return [
            [
                new FlashMessage('Simple Message'),
                'Simple Message',
                '',
                FlashMessage::OK,
                false
            ],
            [
                new FlashMessage('Some OK', 'Message Title', FlashMessage::OK, true),
                'Some OK',
                'Message Title',
                FlashMessage::OK,
                true
            ],
            [
                new FlashMessage('Some Info', 'Message Title', FlashMessage::INFO, true),
                'Some Info',
                'Message Title',
                FlashMessage::INFO,
                true
            ],
            [
                new FlashMessage('Some Notice', 'Message Title', FlashMessage::NOTICE, true),
                'Some Notice',
                'Message Title',
                FlashMessage::NOTICE,
                true
            ],

            [
                new FlashMessage('Some Warning', 'Message Title', FlashMessage::WARNING, true),
                'Some Warning',
                'Message Title',
                FlashMessage::WARNING,
                true
            ],
            [
                new FlashMessage('Some Error', 'Message Title', FlashMessage::ERROR, true),
                'Some Error',
                'Message Title',
                FlashMessage::ERROR,
                true
            ]
        ];
    }

    /**
     * @test
     * @dataProvider addFlashMessageDataProvider
     */
    public function addFlashMessageAddsFlashMessageObjectToFlashMessageQueue($expectedMessage, $messageBody, $messageTitle = '', $severity = FlashMessage::OK, $storeInSession = true)
    {
        $flashMessageQueue = $this->getMockBuilder(FlashMessageQueue::class)
            ->setMethods(['enqueue'])
            ->setConstructorArgs([StringUtility::getUniqueId('identifier_')])
            ->getMock();

        $flashMessageQueue->expects(self::once())->method('enqueue')->with(self::equalTo($expectedMessage));

        $controllerContext = $this->getMockBuilder(ControllerContext::class)
            ->setMethods(['getFlashMessageQueue'])
            ->getMock();
        $controllerContext->expects(self::once())->method('getFlashMessageQueue')->willReturn($flashMessageQueue);

        $controller = $this->getAccessibleMockForAbstractClass(
            ActionController::class,
            [],
            '',
            false,
            true,
            true,
            ['dummy']
        );
        $controller->_set('controllerContext', $controllerContext);

        $controller->addFlashMessage($messageBody, $messageTitle, $severity, $storeInSession);
    }

    /**
     * @test
     */
    public function addFlashMessageThrowsExceptionOnInvalidMessageBody()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1243258395);
        $controller = new ActionController();
        $controller->addFlashMessage(new \stdClass());
    }
}
