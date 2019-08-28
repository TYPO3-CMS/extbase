<?php
namespace TYPO3\CMS\Extbase\Tests\UnitDeprecated\Mvc\Cli;

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

use TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentMixingException;
use TYPO3\CMS\Extbase\Reflection\ClassSchema;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class RequestBuilderTest extends UnitTestCase
{
    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Cli\RequestBuilder|\TYPO3\TestingFramework\Core\AccessibleObjectInterface|\PHPUnit_Framework_Comparator_MockObject
     */
    protected $requestBuilder;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Cli\Request|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $request;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $mockObjectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Cli\Command
     */
    protected $mockCommand;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Cli\CommandManager
     */
    protected $mockCommandManager;

    /**
     * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockReflectionService;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockConfigurationManager;

    /**
     * Sets up this test case
     */
    protected function setUp()
    {
        $this->request = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Mvc\Cli\Request::class, ['dummy']);
        $this->mockObjectManager = $this->createMock(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface::class);
        $this->mockObjectManager->expects($this->any())->method('get')->with(\TYPO3\CMS\Extbase\Mvc\Cli\Request::class)->will($this->returnValue($this->request));
        $this->mockCommand = $this->createMock(\TYPO3\CMS\Extbase\Mvc\Cli\Command::class);
        $this->mockCommand->expects($this->any())->method('getControllerClassName')->will($this->returnValue('Tx\\SomeExtensionName\\Command\\DefaultCommandController'));
        $this->mockCommand->expects($this->any())->method('getControllerCommandName')->will($this->returnValue('list'));
        $this->mockCommandManager = $this->createMock(\TYPO3\CMS\Extbase\Mvc\Cli\CommandManager::class);
        $this->mockCommandManager->expects($this->any())->method('getCommandByIdentifier')->with('some_extension_name:default:list')->will($this->returnValue($this->mockCommand));
        $this->mockReflectionService = $this->createMock(\TYPO3\CMS\Extbase\Reflection\ReflectionService::class);
        $this->mockConfigurationManager = $this->createMock(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::class);
        $this->requestBuilder = $this->getAccessibleMock(\TYPO3\CMS\Extbase\Mvc\Cli\RequestBuilder::class, ['dummy']);
        $this->requestBuilder->_set('objectManager', $this->mockObjectManager);
        $this->requestBuilder->_set('reflectionService', $this->mockReflectionService);
        $this->requestBuilder->_set('commandManager', $this->mockCommandManager);
        $this->requestBuilder->_set('configurationManager', $this->mockConfigurationManager);
    }

    /**
     * Checks if a CLI request specifying a package, controller and action name results in the expected request object
     *
     * @test
     */
    public function cliAccessWithExtensionControllerAndActionNameBuildsCorrectRequest(): void
    {
        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => []
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);
        $request = $this->requestBuilder->build('some_extension_name:default:list');
        $this->assertSame('Tx\\SomeExtensionName\\Command\\DefaultCommandController', $request->getControllerObjectName());
        $this->assertSame('list', $request->getControllerCommandName(), 'The CLI request specifying a package, controller and action name did not return a request object pointing to the expected action.');
    }

    /**
     * @test
     */
    public function ifCommandCantBeResolvedTheHelpScreenIsShown()
    {
        // The following call is only made to satisfy PHPUnit. For some weird reason PHPUnit complains that the
        // mocked method ("getObjectNameByClassName") does not exist _if the mock object is not used_.
        $this->mockCommandManager->getCommandByIdentifier('some_extension_name:default:list');
        $mockCommandManager = $this->createMock(\TYPO3\CMS\Extbase\Mvc\Cli\CommandManager::class);
        $mockCommandManager
            ->expects($this->any())
            ->method('getCommandByIdentifier')
            ->with('test:default:list')
            ->will(
                $this->throwException(
                    new \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchCommandException('testing', 1476050312)
                )
            );
        $this->requestBuilder->_set('commandManager', $mockCommandManager);
        $request = $this->requestBuilder->build('test:default:list');
        $this->assertSame(\TYPO3\CMS\Extbase\Command\HelpCommandController::class, $request->getControllerObjectName());
    }

    /**
     * @test
     */
    public function argumentWithValueSeparatedByEqualSignBuildsCorrectRequest(): void
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);
        $request = $this->requestBuilder->build('some_extension_name:default:list --test-argument=value');
        $this->assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        $this->assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some "console style" (--my-argument=value) arguments results in the expected request object
     *
     * @test
     */
    public function cliAccessWithExtensionControllerActionAndArgumentsBuildsCorrectRequest(): void
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);
        $request = $this->requestBuilder->build('some_extension_name:default:list --test-argument=value --test-argument2=value2');
        $this->assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument2'), 'The given "testArgument2" was not found in the built request.');
        $this->assertEquals($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
        $this->assertEquals($request->getArgument('testArgument2'), 'value2', 'The "testArgument2" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some "console style" (--my-argument =value) arguments with spaces between name and value results in the expected request object
     *
     * @test
     */
    public function checkIfCLIAccesWithPackageControllerActionAndArgumentsToleratesSpaces(): void
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
            'testArgument3' => ['optional' => false, 'type' => 'string'],
            'testArgument4' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);
        $request = $this->requestBuilder->build('some_extension_name:default:list --test-argument= value --test-argument2 =value2 --test-argument3 = value3 --test-argument4=value4');
        $this->assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument2'), 'The given "testArgument2" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument3'), 'The given "testArgument3" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument4'), 'The given "testArgument4" was not found in the built request.');
        $this->assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
        $this->assertSame($request->getArgument('testArgument2'), 'value2', 'The "testArgument2" had not the given value.');
        $this->assertSame($request->getArgument('testArgument3'), 'value3', 'The "testArgument3" had not the given value.');
        $this->assertSame($request->getArgument('testArgument4'), 'value4', 'The "testArgument4" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some short "console style" (-c value or -c=value or -c = value) arguments results in the expected request object
     *
     * @test
     */
    public function CLIAccesWithShortArgumentsBuildsCorrectRequest(): void
    {
        $methodParameters = [
            'a' => ['optional' => false, 'type' => 'string'],
            'd' => ['optional' => false, 'type' => 'string'],
            'f' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $request = $this->requestBuilder->build('some_extension_name:default:list -d valued -f=valuef -a = valuea');
        $this->assertTrue($request->hasArgument('d'), 'The given "d" was not found in the built request.');
        $this->assertTrue($request->hasArgument('f'), 'The given "f" was not found in the built request.');
        $this->assertTrue($request->hasArgument('a'), 'The given "a" was not found in the built request.');
        $this->assertSame($request->getArgument('d'), 'valued', 'The "d" had not the given value.');
        $this->assertSame($request->getArgument('f'), 'valuef', 'The "f" had not the given value.');
        $this->assertSame($request->getArgument('a'), 'valuea', 'The "a" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some mixed "console style" (-c or --my-argument -f=value) arguments with and
     * without values results in the expected request object
     *
     * @test
     */
    public function CLIAccesWithArgumentsWithAndWithoutValuesBuildsCorrectRequest(): void
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
            'testArgument3' => ['optional' => false, 'type' => 'string'],
            'testArgument4' => ['optional' => false, 'type' => 'string'],
            'testArgument5' => ['optional' => false, 'type' => 'string'],
            'testArgument6' => ['optional' => false, 'type' => 'string'],
            'testArgument7' => ['optional' => false, 'type' => 'string'],
            'f' => ['optional' => false, 'type' => 'string'],
            'd' => ['optional' => false, 'type' => 'string'],
            'a' => ['optional' => false, 'type' => 'string'],
            'c' => ['optional' => false, 'type' => 'string'],
            'j' => ['optional' => false, 'type' => 'string'],
            'k' => ['optional' => false, 'type' => 'string'],
            'm' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $request = $this->requestBuilder->build('some_extension_name:default:list --test-argument=value --test-argument2= value2 -k --test-argument-3 = value3 --test-argument4=value4 -f valuef -d=valued -a = valuea -c --testArgument7 --test-argument5 = 5 --test-argument6 -j kjk -m');
        $this->assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument2'), 'The given "testArgument2" was not found in the built request.');
        $this->assertTrue($request->hasArgument('k'), 'The given "k" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument3'), 'The given "testArgument3" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument4'), 'The given "testArgument4" was not found in the built request.');
        $this->assertTrue($request->hasArgument('f'), 'The given "f" was not found in the built request.');
        $this->assertTrue($request->hasArgument('d'), 'The given "d" was not found in the built request.');
        $this->assertTrue($request->hasArgument('a'), 'The given "a" was not found in the built request.');
        $this->assertTrue($request->hasArgument('c'), 'The given "d" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument7'), 'The given "testArgument7" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument5'), 'The given "testArgument5" was not found in the built request.');
        $this->assertTrue($request->hasArgument('testArgument6'), 'The given "testArgument6" was not found in the built request.');
        $this->assertTrue($request->hasArgument('j'), 'The given "j" was not found in the built request.');
        $this->assertTrue($request->hasArgument('m'), 'The given "m" was not found in the built request.');
        $this->assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
        $this->assertSame($request->getArgument('testArgument2'), 'value2', 'The "testArgument2" had not the given value.');
        $this->assertSame($request->getArgument('testArgument3'), 'value3', 'The "testArgument3" had not the given value.');
        $this->assertSame($request->getArgument('testArgument4'), 'value4', 'The "testArgument4" had not the given value.');
        $this->assertSame($request->getArgument('f'), 'valuef', 'The "f" had not the given value.');
        $this->assertSame($request->getArgument('d'), 'valued', 'The "d" had not the given value.');
        $this->assertSame($request->getArgument('a'), 'valuea', 'The "a" had not the given value.');
        $this->assertSame($request->getArgument('testArgument5'), '5', 'The "testArgument4" had not the given value.');
        $this->assertSame($request->getArgument('j'), 'kjk', 'The "j" had not the given value.');
    }

    /**
     * @test
     */
    public function insteadOfNamedArgumentsTheArgumentsCanBePassedUnnamedInTheCorrectOrder(): void
    {
        $methodParameters = [
            'testArgument1' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);
        $request = $this->requestBuilder->build('some_extension_name:default:list --test-argument1 firstArgumentValue --test-argument2 secondArgumentValue');
        $this->assertSame('firstArgumentValue', $request->getArgument('testArgument1'));
        $this->assertSame('secondArgumentValue', $request->getArgument('testArgument2'));
        $request = $this->requestBuilder->build('some_extension_name:default:list firstArgumentValue secondArgumentValue');
        $this->assertSame('firstArgumentValue', $request->getArgument('testArgument1'));
        $this->assertSame('secondArgumentValue', $request->getArgument('testArgument2'));
    }

    /**
     * @test
     */
    public function argumentsAreDetectedAfterOptions(): void
    {
        $methodParameters = [
            'some' => ['optional' => true, 'type' => 'boolean'],
            'option' => ['optional' => true, 'type' => 'string'],
            'argument1' => ['optional' => false, 'type' => 'string'],
            'argument2' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);
        $request = $this->requestBuilder->build('some_extension_name:default:list --some -option=value file1 file2');
        $this->assertSame('list', $request->getControllerCommandName());
        $this->assertTrue($request->getArgument('some'));
        $this->assertSame('file1', $request->getArgument('argument1'));
        $this->assertSame('file2', $request->getArgument('argument2'));
    }

    /**
     * @test
     */
    public function exceedingArgumentsMayBeSpecified(): void
    {
        $methodParameters = [
            'testArgument1' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $expectedArguments = ['testArgument1' => 'firstArgumentValue', 'testArgument2' => 'secondArgumentValue'];
        $request = $this->requestBuilder->build('some_extension_name:default:list --test-argument1=firstArgumentValue --test-argument2 secondArgumentValue exceedingArgument1');
        $this->assertEquals($expectedArguments, $request->getArguments());
        $this->assertEquals(['exceedingArgument1'], $request->getExceedingArguments());
    }

    /**
     * @test
     */
    public function ifNamedArgumentsAreUsedAllRequiredArgumentsMustBeNamed(): void
    {
        $this->expectException(InvalidArgumentMixingException::class);
        $this->expectExceptionCode(1309971820);
        $methodParameters = [
            'testArgument1' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $this->requestBuilder->build('some_extension_name:default:list --test-argument1 firstArgumentValue secondArgumentValue');
    }

    /**
     * @test
     */
    public function ifUnnamedArgumentsAreUsedAllRequiredArgumentsMustBeUnnamed(): void
    {
        $this->expectException(InvalidArgumentMixingException::class);
        $this->expectExceptionCode(1309971821);
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $this->requestBuilder->build('some_extension_name:default:list firstArgumentValue --required-argument2 secondArgumentValue');
    }

    /**
     * @test
     */
    public function booleanOptionsAreConsideredEvenIfAnUnnamedArgumentFollows(): void
    {
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string'],
            'booleanOption' => ['optional' => true, 'type' => 'boolean']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $expectedArguments = ['requiredArgument1' => 'firstArgumentValue', 'requiredArgument2' => 'secondArgumentValue', 'booleanOption' => true];
        $request = $this->requestBuilder->build('some_extension_name:default:list --booleanOption firstArgumentValue secondArgumentValue');
        $this->assertEquals($expectedArguments, $request->getArguments());
    }

    /**
     * @test
     */
    public function booleanOptionsCanHaveOnlyCertainValuesIfTheValueIsAssignedWithoutEqualSign(): void
    {
        $methodParameters = [
            'b1' => ['optional' => true, 'type' => 'boolean'],
            'b2' => ['optional' => true, 'type' => 'boolean'],
            'b3' => ['optional' => true, 'type' => 'boolean'],
            'b4' => ['optional' => true, 'type' => 'boolean'],
            'b5' => ['optional' => true, 'type' => 'boolean'],
            'b6' => ['optional' => true, 'type' => 'boolean']
        ];

        $classSchemaMock = $this->createMock(ClassSchema::class);
        $classSchemaMock->expects($this->any())->method('getMethod')->with('listCommand')->willReturn([
            'params' => $methodParameters
        ]);

        $this->mockReflectionService->expects($this->any())->method('getClassSchema')->with('Tx\\SomeExtensionName\\Command\\DefaultCommandController')->willReturn($classSchemaMock);

        $expectedArguments = ['b1' => true, 'b2' => true, 'b3' => true, 'b4' => false, 'b5' => false, 'b6' => false];
        $request = $this->requestBuilder->build('some_extension_name:default:list --b2 y --b1 1 --b3 true --b4 false --b5 n --b6 0');
        $this->assertEquals($expectedArguments, $request->getArguments());
    }
}
