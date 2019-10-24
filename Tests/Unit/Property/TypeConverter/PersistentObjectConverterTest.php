<?php
namespace TYPO3\CMS\Extbase\Tests\Unit\Property\TypeConverter;

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

use TYPO3\CMS\Extbase\Property\Exception\DuplicateObjectException;
use TYPO3\CMS\Extbase\Property\Exception\InvalidPropertyMappingConfigurationException;
use TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException;
use TYPO3\CMS\Extbase\Property\Exception\TargetNotFoundException;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Reflection\ClassSchema;
use TYPO3\CMS\Extbase\Tests\Unit\Property\TypeConverter\Fixtures\PersistentObjectEntityFixture;
use TYPO3\CMS\Extbase\Tests\Unit\Property\TypeConverter\Fixtures\PersistentObjectFixture;
use TYPO3\CMS\Extbase\Tests\Unit\Property\TypeConverter\Fixtures\PersistentObjectValueObjectFixture;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class PersistentObjectConverterTest extends UnitTestCase
{
    /**
     * @var \TYPO3\CMS\Extbase\Property\TypeConverterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $converter;

    /**
     * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockReflectionService;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockPersistenceManager;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockObjectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Object\Container\Container|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockContainer;

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \RuntimeException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new PersistentObjectConverter();
        $this->mockReflectionService = $this->createMock(\TYPO3\CMS\Extbase\Reflection\ReflectionService::class);
        $this->inject($this->converter, 'reflectionService', $this->mockReflectionService);

        $this->mockPersistenceManager = $this->createMock(\TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface::class);
        $this->inject($this->converter, 'persistenceManager', $this->mockPersistenceManager);

        $this->mockObjectManager = $this->createMock(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface::class);
        $this->mockObjectManager->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($className, ...$arguments) {
                $reflectionClass = new \ReflectionClass($className);
                if (empty($arguments)) {
                    return $reflectionClass->newInstance();
                }
                return $reflectionClass->newInstanceArgs($arguments);
            });
        $this->inject($this->converter, 'objectManager', $this->mockObjectManager);

        $this->mockContainer = $this->createMock(\TYPO3\CMS\Extbase\Object\Container\Container::class);
        $this->inject($this->converter, 'objectContainer', $this->mockContainer);
    }

    /**
     * @test
     */
    public function checkMetadata()
    {
        self::assertEquals(['integer', 'string', 'array'], $this->converter->getSupportedSourceTypes(), 'Source types do not match');
        self::assertEquals('object', $this->converter->getSupportedTargetType(), 'Target type does not match');
        self::assertEquals(20, $this->converter->getPriority(), 'Priority does not match');
    }

    /**
     * @return array
     */
    public function dataProviderForCanConvert()
    {
        return [
            [true, false, true],
            // is entity => can convert
            [false, true, true],
            // is valueobject => can convert
            [false, false, false],
            // is no entity and no value object => can not convert
        ];
    }

    /**
     * @test
     * @dataProvider dataProviderForCanConvert
     */
    public function canConvertFromReturnsTrueIfClassIsTaggedWithEntityOrValueObject($isEntity, $isValueObject, $expected)
    {
        $className = PersistentObjectFixture::class;

        if ($isEntity) {
            $className = PersistentObjectEntityFixture::class;
        } elseif ($isValueObject) {
            $className = PersistentObjectValueObjectFixture::class;
        }
        self::assertEquals($expected, $this->converter->canConvertFrom('myInputData', $className));
    }

    /**
     * @test
     */
    public function getSourceChildPropertiesToBeConvertedReturnsAllPropertiesExceptTheIdentityProperty()
    {
        $source = [
            'k1' => 'v1',
            '__identity' => 'someIdentity',
            'k2' => 'v2'
        ];
        $expected = [
            'k1' => 'v1',
            'k2' => 'v2'
        ];
        self::assertEquals($expected, $this->converter->getSourceChildPropertiesToBeConverted($source));
    }

    /**
     * @test
     */
    public function getTypeOfChildPropertyShouldUseReflectionServiceToDetermineType()
    {
        $mockSchema = $this->getMockBuilder(\TYPO3\CMS\Extbase\Reflection\ClassSchema::class)->disableOriginalConstructor()->getMock();
        $this->mockReflectionService->expects(self::any())->method('getClassSchema')->with('TheTargetType')->willReturn($mockSchema);

        $this->mockContainer->expects(self::any())->method('getImplementationClassName')->willReturn('TheTargetType');
        $mockSchema->expects(self::any())->method('hasProperty')->with('thePropertyName')->willReturn(true);
        $mockSchema->expects(self::any())->method('getProperty')->with('thePropertyName')->willReturn(new ClassSchema\Property(
            'thePropertyName',
            [
                'propertyCharacteristicsBit' => 0,
                't' => 'TheTypeOfSubObject',
                'e' => null,
            ]
        ));
        $configuration = $this->buildConfiguration([]);
        self::assertEquals('TheTypeOfSubObject', $this->converter->getTypeOfChildProperty('TheTargetType', 'thePropertyName', $configuration));
    }

    /**
     * @test
     */
    public function getTypeOfChildPropertyShouldUseConfiguredTypeIfItWasSet()
    {
        $this->mockReflectionService->expects(self::never())->method('getClassSchema');
        $this->mockContainer->expects(self::any())->method('getImplementationClassName')->willReturn('foo');

        $configuration = $this->buildConfiguration([]);
        $configuration->forProperty('thePropertyName')->setTypeConverterOption(\TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_TARGET_TYPE, 'Foo\Bar');
        self::assertEquals('Foo\Bar', $this->converter->getTypeOfChildProperty('foo', 'thePropertyName', $configuration));
    }

    /**
     * @test
     */
    public function convertFromShouldFetchObjectFromPersistenceIfUuidStringIsGiven()
    {
        $identifier = '17';
        $object = new \stdClass();

        $this->mockPersistenceManager->expects(self::any())->method('getObjectByIdentifier')->with($identifier)->willReturn($object);
        self::assertSame($object, $this->converter->convertFrom($identifier, 'MySpecialType'));
    }

    /**
     * @test
     */
    public function convertFromShouldFetchObjectFromPersistenceIfuidStringIsGiven()
    {
        $identifier = '17';
        $object = new \stdClass();

        $this->mockPersistenceManager->expects(self::any())->method('getObjectByIdentifier')->with($identifier)->willReturn($object);
        self::assertSame($object, $this->converter->convertFrom($identifier, 'MySpecialType'));
    }

    /**
     * @test
     */
    public function convertFromShouldFetchObjectFromPersistenceIfOnlyIdentityArrayGiven()
    {
        $identifier = '12345';
        $object = new \stdClass();

        $source = [
            '__identity' => $identifier
        ];
        $this->mockPersistenceManager->expects(self::any())->method('getObjectByIdentifier')->with($identifier)->willReturn($object);
        self::assertSame($object, $this->converter->convertFrom($source, 'MySpecialType'));
    }

    /**
     * @test
     */
    public function convertFromShouldThrowExceptionIfObjectNeedsToBeModifiedButConfigurationIsNotSet()
    {
        $this->expectException(InvalidPropertyMappingConfigurationException::class);
        $this->expectExceptionCode(1297932028);
        $identifier = '12345';
        $object = new \stdClass();
        $object->someProperty = 'asdf';

        $source = [
            '__identity' => $identifier,
            'foo' => 'bar'
        ];
        $this->mockPersistenceManager->expects(self::any())->method('getObjectByIdentifier')->with($identifier)->willReturn($object);
        $this->converter->convertFrom($source, 'MySpecialType');
    }

    /**
     * @param array $typeConverterOptions
     * @return \TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration
     */
    protected function buildConfiguration($typeConverterOptions)
    {
        $configuration = new \TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration();
        $configuration->setTypeConverterOptions(\TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter::class, $typeConverterOptions);
        return $configuration;
    }

    /**
     * @param int $numberOfResults
     * @param Matcher $howOftenIsGetFirstCalled
     * @return \stdClass
     */
    public function setupMockQuery($numberOfResults, $howOftenIsGetFirstCalled)
    {
        $mockClassSchema = $this->getMockBuilder(\TYPO3\CMS\Extbase\Reflection\ClassSchema::class)
            ->setConstructorArgs([\TYPO3\CMS\Extbase\Tests\Unit\Property\TypeConverter\Fixtures\Query::class])
            ->getMock();
        $this->mockReflectionService->expects(self::any())->method('getClassSchema')->with('SomeType')->willReturn($mockClassSchema);

        $mockConstraint = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\Generic\Qom\Comparison::class)->disableOriginalConstructor()->getMock();

        $mockObject = new \stdClass();
        $mockQuery = $this->createMock(\TYPO3\CMS\Extbase\Persistence\QueryInterface::class);
        $mockQueryResult = $this->createMock(\TYPO3\CMS\Extbase\Persistence\QueryResultInterface::class);
        $mockQueryResult->expects(self::any())->method('count')->willReturn($numberOfResults);
        $mockQueryResult->expects($howOftenIsGetFirstCalled)->method('getFirst')->willReturn($mockObject);
        $mockQuery->expects(self::any())->method('equals')->with('key1', 'value1')->willReturn($mockConstraint);
        $mockQuery->expects(self::any())->method('matching')->with($mockConstraint)->willReturn($mockQuery);
        $mockQuery->expects(self::any())->method('execute')->willReturn($mockQueryResult);

        $this->mockPersistenceManager->expects(self::any())->method('createQueryForType')->with('SomeType')->willReturn($mockQuery);

        return $mockObject;
    }

    /**
     * @test
     */
    public function convertFromShouldReturnExceptionIfNoMatchingObjectWasFound()
    {
        $this->expectException(TargetNotFoundException::class);
        $this->expectExceptionCode(1297933823);
        $this->setupMockQuery(0, self::never());
        $this->mockReflectionService->expects(self::never())->method('getClassSchema');

        $source = [
            '__identity' => 123
        ];
        $actual = $this->converter->convertFrom($source, 'SomeType');
        self::assertNull($actual);
    }

    /**
     * @test
     */
    public function convertFromShouldThrowExceptionIfMoreThanOneObjectWasFound()
    {
        $this->expectException(DuplicateObjectException::class);
        // @TODO expectExceptionCode is 0
        $this->setupMockQuery(2, self::never());

        $source = [
            '__identity' => 666
        ];
        $this->mockPersistenceManager
            ->expects(self::any())
            ->method('getObjectByIdentifier')
            ->with(666)
            ->will(self::throwException(new DuplicateObjectException('testing', 1476107580)));
        $this->converter->convertFrom($source, 'SomeType');
    }

    /**
     * @test
     */
    public function convertFromShouldThrowExceptionIfObjectNeedsToBeCreatedButConfigurationIsNotSet()
    {
        $this->expectException(InvalidPropertyMappingConfigurationException::class);
        // @TODO expectExceptionCode is 0
        $source = [
            'foo' => 'bar'
        ];
        $this->converter->convertFrom($source, 'MySpecialType');
    }

    /**
     * @test
     */
    public function convertFromShouldCreateObject()
    {
        $source = [
            'propertyX' => 'bar'
        ];
        $convertedChildProperties = [
            'property1' => 'bar'
        ];
        $expectedObject = new \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSetters();
        $expectedObject->property1 = 'bar';

        $configuration = $this->buildConfiguration([PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true]);
        $result = $this->converter->convertFrom($source, \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSetters::class, $convertedChildProperties, $configuration);
        self::assertEquals($expectedObject, $result);
    }

    /**
     * @test
     */
    public function convertFromShouldThrowExceptionIfPropertyOnTargetObjectCouldNotBeSet()
    {
        $this->expectException(InvalidTargetException::class);
        $this->expectExceptionCode(1297935345);
        $source = [
            'propertyX' => 'bar'
        ];
        $object = new \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSetters();
        $convertedChildProperties = [
            'propertyNotExisting' => 'bar'
        ];
        $this->mockObjectManager->expects(self::any())->method('get')->with(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSetters::class)->willReturn($object);
        $configuration = $this->buildConfiguration([PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true]);
        $result = $this->converter->convertFrom($source, \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSetters::class, $convertedChildProperties, $configuration);
        self::assertSame($object, $result);
    }

    /**
     * @test
     */
    public function convertFromShouldCreateObjectWhenThereAreConstructorParameters(): void
    {
        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock
            ->expects(self::any())
            ->method('getMethod')
            ->with('__construct')
            ->willReturn(
                new ClassSchema\Method(
                    '__construct',
                    [
                        'params' => [
                            'property1' => ['optional' => false]
                        ]
                    ],
                    get_class($classSchemaMock)
                )
            );

        $classSchemaMock
            ->expects(self::any())
            ->method('hasConstructor')
            ->willReturn(true);

        $this->mockReflectionService
            ->expects(self::any())
            ->method('getClassSchema')
            ->with(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class)
            ->willReturn($classSchemaMock);

        $source = [
            'propertyX' => 'bar'
        ];
        $convertedChildProperties = [
            'property1' => 'param1',
            'property2' => 'bar'
        ];
        $expectedObject = new \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor('param1');
        $expectedObject->setProperty2('bar');

        $this->mockContainer->expects(self::any())->method('getImplementationClassName')->willReturn(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class);
        $configuration = $this->buildConfiguration([PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true]);
        $result = $this->converter->convertFrom($source, \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class, $convertedChildProperties, $configuration);
        self::assertEquals($expectedObject, $result);
        self::assertEquals('bar', $expectedObject->getProperty2());
    }

    /**
     * @test
     */
    public function convertFromShouldCreateObjectWhenThereAreOptionalConstructorParameters()
    {
        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock
            ->expects(self::any())
            ->method('getMethod')
            ->with('__construct')
            ->willReturn(
                new ClassSchema\Method(
                    '__construct',
                    [
                        'params' => [
                            'property1' => ['optional' => true, 'defaultValue' => 'thisIsTheDefaultValue']
                        ]
                    ],
                    get_class($classSchemaMock)
                )
            );

        $classSchemaMock
            ->expects(self::any())
            ->method('hasConstructor')
            ->willReturn(true);

        $this->mockReflectionService
            ->expects(self::any())
            ->method('getClassSchema')
            ->with(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class)
            ->willReturn($classSchemaMock);

        $source = [
            'propertyX' => 'bar'
        ];
        $expectedObject = new \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor('thisIsTheDefaultValue');

        $this->mockContainer->expects(self::any())->method('getImplementationClassName')->willReturn(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class);
        $configuration = $this->buildConfiguration([PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true]);
        $result = $this->converter->convertFrom($source, \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class, [], $configuration);
        self::assertEquals($expectedObject, $result);
    }

    /**
     * @test
     */
    public function convertFromShouldThrowExceptionIfRequiredConstructorParameterWasNotFound(): void
    {
        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock
            ->expects(self::any())
            ->method('getMethod')
            ->with('__construct')
            ->willReturn(
                new ClassSchema\Method(
                    '__construct',
                    [
                        'params' => [
                            'property1' => ['optional' => false]
                        ]
                    ],
                    get_class($classSchemaMock)
                )
            );

        $classSchemaMock
            ->expects(self::any())
            ->method('hasConstructor')
            ->willReturn(true);

        $this->mockReflectionService
            ->expects(self::any())
            ->method('getClassSchema')
            ->with(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class)
            ->willReturn($classSchemaMock);

        $this->expectException(InvalidTargetException::class);
        $this->expectExceptionCode(1268734872);
        $source = [
            'propertyX' => 'bar'
        ];
        $object = new \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor('param1');
        $convertedChildProperties = [
            'property2' => 'bar'
        ];

        $this->mockContainer->expects(self::any())->method('getImplementationClassName')->willReturn(\TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class);
        $configuration = $this->buildConfiguration([PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED => true]);
        $result = $this->converter->convertFrom($source, \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class, $convertedChildProperties, $configuration);
        self::assertSame($object, $result);
    }

    /**
     * @test
     */
    public function convertFromShouldReturnNullForEmptyString()
    {
        $source = '';
        $result = $this->converter->convertFrom($source, \TYPO3\CMS\Extbase\Tests\Fixture\ClassWithSettersAndConstructor::class);
        self::assertNull($result);
    }
}
