<?php

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

namespace TYPO3\CMS\Extbase\Persistence\Generic;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\InvalidNumberOfConstraintsException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnexpectedTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\QueryObjectModelFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\SelectorInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\SourceInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Utility\TypeHandlingUtility;

/**
 * The Query class used to run queries against the database
 */
class Query implements QueryInterface
{
    /**
     * An inner join.
     */
    const JCR_JOIN_TYPE_INNER = '{http://www.jcp.org/jcr/1.0}joinTypeInner';

    /**
     * A left-outer join.
     */
    const JCR_JOIN_TYPE_LEFT_OUTER = '{http://www.jcp.org/jcr/1.0}joinTypeLeftOuter';

    /**
     * A right-outer join.
     */
    const JCR_JOIN_TYPE_RIGHT_OUTER = '{http://www.jcp.org/jcr/1.0}joinTypeRightOuter';

    /**
     * Charset of strings in QOM
     */
    const CHARSET = 'utf-8';

    /**
     * @var string
     */
    protected $type;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory
     */
    protected $dataMapFactory;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\Qom\QueryObjectModelFactory
     */
    protected $qomFactory;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\Qom\SourceInterface
     */
    protected $source;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface
     */
    protected $constraint;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Statement
     */
    protected $statement;

    /**
     * @var int[]
     */
    protected $orderings = [];

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset;

    /**
     * The query settings.
     *
     * @var QuerySettingsInterface
     */
    protected $querySettings;

    /**
     * @var QueryInterface|null
     * @internal
     */
    protected $parentQuery;

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory $dataMapFactory
     */
    public function injectDataMapFactory(DataMapFactory $dataMapFactory)
    {
        $this->dataMapFactory = $dataMapFactory;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface $persistenceManager
     */
    public function injectPersistenceManager(PersistenceManagerInterface $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\Qom\QueryObjectModelFactory $qomFactory
     */
    public function injectQomFactory(QueryObjectModelFactory $qomFactory)
    {
        $this->qomFactory = $qomFactory;
    }

    /**
     * Constructs a query object working on the given class name
     *
     * @param string $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @return ?QueryInterface
     * @internal
     */
    public function getParentQuery(): ?QueryInterface
    {
        return $this->parentQuery;
    }

    /**
     * @param ?QueryInterface $parentQuery
     * @internal
     */
    public function setParentQuery(?QueryInterface $parentQuery): void
    {
        $this->parentQuery = $parentQuery;
    }

    /**
     * Sets the Query Settings. These Query settings must match the settings expected by
     * the specific Storage Backend.
     *
     * @param QuerySettingsInterface $querySettings The Query Settings
     */
    public function setQuerySettings(QuerySettingsInterface $querySettings)
    {
        $this->querySettings = $querySettings;
    }

    /**
     * Returns the Query Settings.
     *
     * @throws Exception
     * @return QuerySettingsInterface $querySettings The Query Settings
     */
    public function getQuerySettings()
    {
        if (!$this->querySettings instanceof QuerySettingsInterface) {
            throw new Exception('Tried to get the query settings without setting them before.', 1248689115);
        }
        return $this->querySettings;
    }

    /**
     * Returns the type this query cares for.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the source to fetch the result from
     *
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\Qom\SourceInterface $source
     */
    public function setSource(SourceInterface $source)
    {
        $this->source = $source;
    }

    /**
     * Returns the selector's name or an empty string, if the source is not a selector
     * @todo This has to be checked at another place
     *
     * @return string The selector name
     */
    protected function getSelectorName()
    {
        $source = $this->getSource();
        if ($source instanceof SelectorInterface) {
            return $source->getSelectorName();
        }
        return '';
    }

    /**
     * Gets the node-tuple source for this query.
     *
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\SourceInterface the node-tuple source; non-null
     */
    public function getSource()
    {
        if ($this->source === null) {
            $this->source = $this->qomFactory->selector($this->getType(), $this->dataMapFactory->buildDataMap($this->getType())->getTableName());
        }
        return $this->source;
    }

    /**
     * Executes the query against the database and returns the result
     *
     * @param bool $returnRawQueryResult avoids the object mapping by the persistence
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface|array The query result object or an array if $returnRawQueryResult is TRUE
     */
    public function execute($returnRawQueryResult = false)
    {
        if ($returnRawQueryResult) {
            return $this->persistenceManager->getObjectDataByQuery($this);
        }
        return $this->objectManager->get(QueryResultInterface::class, $this);
    }

    /**
     * Sets the property names to order the result by. Expected like this:
     * array(
     * 'foo' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
     * 'bar' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING
     * )
     * where 'foo' and 'bar' are property names.
     *
     * @param array $orderings The property names to order by
     * @return QueryInterface
     */
    public function setOrderings(array $orderings)
    {
        $this->orderings = $orderings;
        return $this;
    }

    /**
     * Returns the property names to order the result by. Like this:
     * array(
     * 'foo' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
     * 'bar' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING
     * )
     *
     * @return int
     */
    public function getOrderings()
    {
        return $this->orderings;
    }

    /**
     * Sets the maximum size of the result set to limit. Returns $this to allow
     * for chaining (fluid interface)
     *
     * @param int $limit
     * @throws \InvalidArgumentException
     * @return QueryInterface
     */
    public function setLimit($limit)
    {
        if (!is_int($limit) || $limit < 1) {
            throw new \InvalidArgumentException('The limit must be an integer >= 1', 1245071870);
        }
        $this->limit = $limit;
        return $this;
    }

    /**
     * Resets a previously set maximum size of the result set. Returns $this to allow
     * for chaining (fluid interface)
     *
     * @return QueryInterface
     */
    public function unsetLimit()
    {
        unset($this->limit);
        return $this;
    }

    /**
     * Returns the maximum size of the result set to limit.
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Sets the start offset of the result set to offset. Returns $this to
     * allow for chaining (fluid interface)
     *
     * @param int $offset
     * @throws \InvalidArgumentException
     * @return QueryInterface
     */
    public function setOffset($offset)
    {
        if (!is_int($offset) || $offset < 0) {
            throw new \InvalidArgumentException('The offset must be a positive integer', 1245071872);
        }
        $this->offset = $offset;
        return $this;
    }

    /**
     * Returns the start offset of the result set.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * The constraint used to limit the result set. Returns $this to allow
     * for chaining (fluid interface)
     *
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface $constraint
     * @return QueryInterface
     */
    public function matching($constraint)
    {
        $this->constraint = $constraint;
        return $this;
    }

    /**
     * Sets the statement of this query. If you use this, you will lose the abstraction from a concrete storage
     * backend (database).
     *
     * @param string|\TYPO3\CMS\Core\Database\Query\QueryBuilder|\Doctrine\DBAL\Statement $statement The statement
     * @param array $parameters An array of parameters. These will be bound to placeholders '?' in the $statement.
     * @return QueryInterface
     */
    public function statement($statement, array $parameters = [])
    {
        $this->statement = $this->qomFactory->statement($statement, $parameters);
        return $this;
    }

    /**
     * Returns the statement of this query.
     *
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * Gets the constraint for this query.
     *
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface|null the constraint, or null if none
     */
    public function getConstraint()
    {
        return $this->constraint;
    }

    /**
     * Performs a logical conjunction of the given constraints. The method takes one or more constraints and concatenates them with a boolean AND.
     * It also accepts a single array of constraints to be concatenated.
     *
     * @param mixed $constraint1 The first of multiple constraints or an array of constraints.
     * @throws Exception\InvalidNumberOfConstraintsException
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\AndInterface
     */
    public function logicalAnd($constraint1)
    {
        if (is_array($constraint1)) {
            $resultingConstraint = array_shift($constraint1);
            $constraints = $constraint1;
        } else {
            $constraints = func_get_args();
            $resultingConstraint = array_shift($constraints);
        }
        if ($resultingConstraint === null) {
            throw new InvalidNumberOfConstraintsException('There must be at least one constraint or a non-empty array of constraints given.', 1268056288);
        }
        foreach ($constraints as $constraint) {
            $resultingConstraint = $this->qomFactory->_and($resultingConstraint, $constraint);
        }
        return $resultingConstraint;
    }

    /**
     * Performs a logical disjunction of the two given constraints
     *
     * @param mixed $constraint1 The first of multiple constraints or an array of constraints.
     * @throws Exception\InvalidNumberOfConstraintsException
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\OrInterface
     */
    public function logicalOr($constraint1)
    {
        if (is_array($constraint1)) {
            $resultingConstraint = array_shift($constraint1);
            $constraints = $constraint1;
        } else {
            $constraints = func_get_args();
            $resultingConstraint = array_shift($constraints);
        }
        if ($resultingConstraint === null) {
            throw new InvalidNumberOfConstraintsException('There must be at least one constraint or a non-empty array of constraints given.', 1268056289);
        }
        foreach ($constraints as $constraint) {
            $resultingConstraint = $this->qomFactory->_or($resultingConstraint, $constraint);
        }
        return $resultingConstraint;
    }

    /**
     * Performs a logical negation of the given constraint
     *
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface $constraint Constraint to negate
     * @throws \RuntimeException
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\NotInterface
     */
    public function logicalNot(ConstraintInterface $constraint)
    {
        return $this->qomFactory->not($constraint);
    }

    /**
     * Returns an equals criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with
     * @param bool $caseSensitive Whether the equality test should be done case-sensitive
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function equals($propertyName, $operand, $caseSensitive = true)
    {
        if (is_object($operand) || $caseSensitive) {
            $comparison = $this->qomFactory->comparison(
                $this->qomFactory->propertyValue($propertyName, $this->getSelectorName()),
                QueryInterface::OPERATOR_EQUAL_TO,
                $operand
            );
        } else {
            $comparison = $this->qomFactory->comparison(
                $this->qomFactory->lowerCase($this->qomFactory->propertyValue($propertyName, $this->getSelectorName())),
                QueryInterface::OPERATOR_EQUAL_TO,
                mb_strtolower($operand, \TYPO3\CMS\Extbase\Persistence\Generic\Query::CHARSET)
            );
        }
        return $comparison;
    }

    /**
     * Returns a like criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function like($propertyName, $operand)
    {
        return $this->qomFactory->comparison(
            $this->qomFactory->propertyValue($propertyName, $this->getSelectorName()),
            QueryInterface::OPERATOR_LIKE,
            $operand
        );
    }

    /**
     * Returns a "contains" criterion used for matching objects against a query.
     * It matches if the multivalued property contains the given operand.
     *
     * @param string $propertyName The name of the (multivalued) property to compare against
     * @param mixed $operand The value to compare with
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function contains($propertyName, $operand)
    {
        return $this->qomFactory->comparison($this->qomFactory->propertyValue($propertyName, $this->getSelectorName()), QueryInterface::OPERATOR_CONTAINS, $operand);
    }

    /**
     * Returns an "in" criterion used for matching objects against a query. It
     * matches if the property's value is contained in the multivalued operand.
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with, multivalued
     * @throws Exception\UnexpectedTypeException
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function in($propertyName, $operand)
    {
        if (!TypeHandlingUtility::isValidTypeForMultiValueComparison($operand)) {
            throw new UnexpectedTypeException('The "in" operator must be given a multivalued operand (array, ArrayAccess, Traversable).', 1264678095);
        }
        return $this->qomFactory->comparison($this->qomFactory->propertyValue($propertyName, $this->getSelectorName()), QueryInterface::OPERATOR_IN, $operand);
    }

    /**
     * Returns a less than criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function lessThan($propertyName, $operand)
    {
        return $this->qomFactory->comparison($this->qomFactory->propertyValue($propertyName, $this->getSelectorName()), QueryInterface::OPERATOR_LESS_THAN, $operand);
    }

    /**
     * Returns a less or equal than criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function lessThanOrEqual($propertyName, $operand)
    {
        return $this->qomFactory->comparison($this->qomFactory->propertyValue($propertyName, $this->getSelectorName()), QueryInterface::OPERATOR_LESS_THAN_OR_EQUAL_TO, $operand);
    }

    /**
     * Returns a greater than criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function greaterThan($propertyName, $operand)
    {
        return $this->qomFactory->comparison($this->qomFactory->propertyValue($propertyName, $this->getSelectorName()), QueryInterface::OPERATOR_GREATER_THAN, $operand);
    }

    /**
     * Returns a greater than or equal criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operand The value to compare with
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface
     */
    public function greaterThanOrEqual($propertyName, $operand)
    {
        return $this->qomFactory->comparison($this->qomFactory->propertyValue($propertyName, $this->getSelectorName()), QueryInterface::OPERATOR_GREATER_THAN_OR_EQUAL_TO, $operand);
    }

    /**
     * Returns a greater than or equal criterion used for matching objects against a query
     *
     * @param string $propertyName The name of the property to compare against
     * @param mixed $operandLower The value of the lower boundary to compare against
     * @param mixed $operandUpper The value of the upper boundary to compare against
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\AndInterface
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\InvalidNumberOfConstraintsException
     */
    public function between($propertyName, $operandLower, $operandUpper)
    {
        return $this->logicalAnd(
            $this->greaterThanOrEqual($propertyName, $operandLower),
            $this->lessThanOrEqual($propertyName, $operandUpper)
        );
    }

    /**
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function __wakeup()
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->persistenceManager = $this->objectManager->get(PersistenceManagerInterface::class);
        $this->dataMapFactory = $this->objectManager->get(DataMapFactory::class);
        $this->qomFactory = $this->objectManager->get(QueryObjectModelFactory::class);
    }

    /**
     * @return array
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function __sleep()
    {
        return ['type', 'source', 'constraint', 'statement', 'orderings', 'limit', 'offset', 'querySettings'];
    }

    /**
     * Returns the query result count.
     *
     * @return int The query result count
     */
    public function count()
    {
        return $this->execute()->count();
    }

    /**
     * Returns an "isEmpty" criterion used for matching objects against a query.
     * It matches if the multivalued property contains no values or is NULL.
     *
     * @param string $propertyName The name of the multivalued property to compare against
     * @throws Exception\NotImplementedException
     */
    public function isEmpty($propertyName)
    {
        throw new NotImplementedException(__METHOD__, 1476122265);
    }
}
