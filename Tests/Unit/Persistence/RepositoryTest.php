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

namespace TYPO3\CMS\Extbase\Tests\Unit\Persistence;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Backend;
use TYPO3\CMS\Extbase\Persistence\Generic\BackendInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedMethodException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Tests\Unit\Persistence\Fixture\Domain\Model\Entity;
use TYPO3\CMS\Extbase\Tests\Unit\Persistence\Fixture\Domain\Repository\EntityRepository;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RepositoryTest extends UnitTestCase
{
    protected Repository&MockObject&AccessibleObjectInterface $repository;

    /**
     * @var QueryFactory
     */
    protected $mockQueryFactory;

    /**
     * @var BackendInterface
     */
    protected $mockBackend;

    /**
     * @var Session
     */
    protected $mockSession;

    /**
     * @var PersistenceManagerInterface
     */
    protected $mockPersistenceManager;

    /**
     * @var QueryInterface
     */
    protected $mockQuery;

    /**
     * @var QuerySettingsInterface
    */
    protected $mockQuerySettings;

    /**
     * @var ConfigurationManager
     */
    protected $mockConfigurationManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockQueryFactory = $this->createMock(QueryFactory::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);
        $this->mockQuerySettings = $this->createMock(QuerySettingsInterface::class);
        $this->mockQuery->method('getQuerySettings')->willReturn($this->mockQuerySettings);
        $this->mockQueryFactory->method('create')->willReturn($this->mockQuery);
        $this->mockSession = $this->createMock(Session::class);
        $this->mockConfigurationManager = $this->createMock(ConfigurationManager::class);
        $this->mockBackend = $this->getAccessibleMock(Backend::class, null, [$this->mockConfigurationManager], '', false);
        $this->mockBackend->_set('session', $this->mockSession);
        $this->mockPersistenceManager = $this->getAccessibleMock(
            PersistenceManager::class,
            ['createQueryForType'],
            [
                $this->mockQueryFactory,
                $this->mockBackend,
                $this->mockSession,
            ]
        );
        $this->mockBackend->setPersistenceManager($this->mockPersistenceManager);
        $this->mockPersistenceManager->method('createQueryForType')->willReturn($this->mockQuery);
        $this->repository = $this->getAccessibleMock(Repository::class, null);
        $this->repository->injectPersistenceManager($this->mockPersistenceManager);
    }

    #[Test]
    public function abstractRepositoryImplementsRepositoryInterface(): void
    {
        self::assertInstanceOf(RepositoryInterface::class, $this->repository);
    }

    #[Test]
    public function createQueryCallsPersistenceManagerWithExpectedClassName(): void
    {
        $mockPersistenceManager = $this->createMock(PersistenceManager::class);
        $mockPersistenceManager->expects(self::once())->method('createQueryForType')->with('ExpectedType');

        $this->repository->_set('objectType', 'ExpectedType');
        $this->repository->injectPersistenceManager($mockPersistenceManager);

        $this->repository->createQuery();
    }

    #[Test]
    public function createQuerySetsDefaultOrderingIfDefined(): void
    {
        $orderings = ['foo' => QueryInterface::ORDER_ASCENDING];
        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->expects(self::once())->method('setOrderings')->with($orderings);
        $mockPersistenceManager = $this->createMock(PersistenceManager::class);
        $mockPersistenceManager->expects(self::exactly(2))->method('createQueryForType')->with('ExpectedType')->willReturn($mockQuery);

        $this->repository->_set('objectType', 'ExpectedType');
        $this->repository->injectPersistenceManager($mockPersistenceManager);
        $this->repository->setDefaultOrderings($orderings);
        $this->repository->createQuery();

        $this->repository->setDefaultOrderings([]);
        $this->repository->createQuery();
    }

    #[Test]
    public function findAllCreatesQueryAndReturnsResultOfExecuteCall(): void
    {
        $expectedResult = $this->createMock(QueryResultInterface::class);

        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->expects(self::once())->method('execute')->with()->willReturn($expectedResult);

        $repository = $this->getMockBuilder(Repository::class)
            ->onlyMethods(['createQuery'])
            ->getMock();
        $repository->expects(self::once())->method('createQuery')->willReturn($mockQuery);

        self::assertSame($expectedResult, $repository->findAll());
    }

    #[Test]
    public function findByIdentifierReturnsResultOfGetObjectByIdentifierCallFromBackend(): void
    {
        $identifier = '42';
        $object = new \stdClass();

        $expectedResult = $this->createMock(QueryResultInterface::class);
        $expectedResult->expects(self::once())->method('getFirst')->willReturn($object);

        $this->mockQuery->method('getQuerySettings')->willReturn($this->mockQuerySettings);
        $this->mockQuery->expects(self::once())->method('matching')->willReturn($this->mockQuery);
        $this->mockQuery->expects(self::once())->method('execute')->willReturn($expectedResult);

        // skip backend, as we want to test the backend
        $this->mockSession->method('hasIdentifier')->willReturn(false);
        self::assertSame($object, $this->repository->findByIdentifier($identifier));
    }

    #[Test]
    public function addDelegatesToPersistenceManager(): void
    {
        $object = new \stdClass();
        $mockPersistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $mockPersistenceManager->expects(self::once())->method('add')->with($object);
        $this->repository->injectPersistenceManager($mockPersistenceManager);
        $this->repository->_set('objectType', get_class($object));
        $this->repository->add($object);
    }

    #[Test]
    public function removeDelegatesToPersistenceManager(): void
    {
        $object = new \stdClass();
        $mockPersistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $mockPersistenceManager->expects(self::once())->method('remove')->with($object);
        $this->repository->injectPersistenceManager($mockPersistenceManager);
        $this->repository->_set('objectType', get_class($object));
        $this->repository->remove($object);
    }

    #[Test]
    public function updateDelegatesToPersistenceManager(): void
    {
        $object = new \stdClass();
        $mockPersistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $mockPersistenceManager->expects(self::once())->method('update')->with($object);
        $this->repository->injectPersistenceManager($mockPersistenceManager);
        $this->repository->_set('objectType', get_class($object));
        $this->repository->update($object);
    }

    #[Test]
    public function magicCallMethodTriggersAnErrorIfUnknownMethodsAreCalled(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionCode(1233180480);
        $repository = $this->getMockBuilder(Repository::class)
            ->onlyMethods(['createQuery'])
            ->getMock();
        $repository->__call('foo', []);
    }

    #[Test]
    public function addChecksObjectType(): void
    {
        $this->expectException(IllegalObjectTypeException::class);
        $this->expectExceptionCode(1248363335);
        $this->repository->_set('objectType', 'ExpectedObjectType');
        $this->repository->add(new \stdClass());
    }

    #[Test]
    public function removeChecksObjectType(): void
    {
        $this->expectException(IllegalObjectTypeException::class);
        $this->expectExceptionCode(1248363336);
        $this->repository->_set('objectType', 'ExpectedObjectType');
        $this->repository->remove(new \stdClass());
    }

    #[Test]
    public function updateChecksObjectType(): void
    {
        $this->expectException(IllegalObjectTypeException::class);
        $this->expectExceptionCode(1249479625);
        $repository = $this->getAccessibleMock(Repository::class, null);
        $repository->_set('objectType', 'ExpectedObjectType');

        $repository->update(new \stdClass());
    }

    #[Test]
    public function constructSetsObjectTypeFromClassName(): void
    {
        $repository = new EntityRepository();
        $reflectionClass = new \ReflectionClass($repository);
        $reflectionProperty = $reflectionClass->getProperty('objectType');
        $objectType = $reflectionProperty->getValue($repository);

        self::assertEquals(Entity::class, $objectType);
    }

    #[Test]
    public function createQueryReturnsQueryWithUnmodifiedDefaultQuerySettings(): void
    {
        $this->mockQuery = $this->createMock(Query::class);
        $mockDefaultQuerySettings = $this->createMock(QuerySettingsInterface::class);
        $this->repository->setDefaultQuerySettings($mockDefaultQuerySettings);
        $query = $this->repository->createQuery();
        $instanceQuerySettings = $query->getQuerySettings();
        self::assertEquals($mockDefaultQuerySettings, $instanceQuerySettings);
        self::assertNotSame($mockDefaultQuerySettings, $instanceQuerySettings);
    }

    #[Test]
    public function findByUidReturnsResultOfGetObjectByIdentifierCall(): void
    {
        $fakeUid = '123';
        $object = new \stdClass();
        $repository = $this->getMockBuilder(Repository::class)
            ->onlyMethods(['findByIdentifier'])
            ->getMock();
        $expectedResult = $object;
        $repository->expects(self::once())->method('findByIdentifier')->willReturn($object);
        $actualResult = $repository->findByUid($fakeUid);
        self::assertSame($expectedResult, $actualResult);
    }

    #[Test]
    public function updateRejectsObjectsOfWrongType(): void
    {
        $this->expectException(IllegalObjectTypeException::class);
        $this->expectExceptionCode(1249479625);
        $this->repository->_set('objectType', 'Foo');
        $this->repository->update(new \stdClass());
    }
}
