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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Object\Container\Container;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * The persistence session - acts as a Unit of Work for Extbase persistence framework.
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class Session implements SingletonInterface
{
    /**
     * @var \TYPO3\CMS\Extbase\Object\Container\Container
     */
    protected $objectContainer;

    /**
     * Reconstituted objects
     *
     * @var ObjectStorage
     */
    protected $reconstitutedEntities;

    /**
     * @var ObjectStorage
     */
    protected $objectMap;

    /**
     * @var array
     */
    protected $identifierMap = [];

    /**
     * Constructs a new Session
     */
    public function __construct(Container $container)
    {
        $this->objectContainer = $container;
        $this->reconstitutedEntities = new ObjectStorage();
        $this->objectMap = new ObjectStorage();
    }

    /**
     * Registers data for a reconstituted object.
     *
     * $entityData format is described in
     * "Documentation/PersistenceFramework object data format.txt"
     *
     * @param object $entity
     */
    public function registerReconstitutedEntity($entity)
    {
        $this->reconstitutedEntities->attach($entity);
    }

    /**
     * Replace a reconstituted object, leaves the clean data unchanged.
     *
     * @param object $oldEntity
     * @param object $newEntity
     */
    public function replaceReconstitutedEntity($oldEntity, $newEntity)
    {
        $this->reconstitutedEntities->detach($oldEntity);
        $this->reconstitutedEntities->attach($newEntity);
    }

    /**
     * Unregisters data for a reconstituted object
     *
     * @param object $entity
     */
    public function unregisterReconstitutedEntity($entity)
    {
        if ($this->reconstitutedEntities->contains($entity)) {
            $this->reconstitutedEntities->detach($entity);
        }
    }

    /**
     * Returns all objects which have been registered as reconstituted
     *
     * @return ObjectStorage All reconstituted objects
     */
    public function getReconstitutedEntities()
    {
        return $this->reconstitutedEntities;
    }

    /**
     * Tells whether the given object is a reconstituted entity.
     *
     * @param object $entity
     * @return bool
     */
    public function isReconstitutedEntity($entity)
    {
        return $this->reconstitutedEntities->contains($entity);
    }

    // @todo implement the is dirty checking behaviour of the Flow persistence session here

    /**
     * Checks whether the given object is known to the identity map
     *
     * @param object $object
     * @return bool
     */
    public function hasObject($object)
    {
        return $this->objectMap->contains($object);
    }

    /**
     * Checks whether the given identifier is known to the identity map
     *
     * @param string $identifier
     * @param string $className
     * @return bool
     */
    public function hasIdentifier($identifier, $className)
    {
        return isset($this->identifierMap[$this->getClassIdentifier($className)][$identifier]);
    }

    /**
     * Returns the object for the given identifier
     *
     * @param string $identifier
     * @param string $className
     * @return object
     */
    public function getObjectByIdentifier($identifier, $className)
    {
        return $this->identifierMap[$this->getClassIdentifier($className)][$identifier];
    }

    /**
     * Returns the identifier for the given object from
     * the session, if the object was registered.
     *
     *
     * @param object $object
     * @return string
     */
    public function getIdentifierByObject($object)
    {
        if ($this->hasObject($object)) {
            return $this->objectMap[$object];
        }
        return null;
    }

    /**
     * Register an identifier for an object
     *
     * @param object $object
     * @param string $identifier
     */
    public function registerObject($object, $identifier)
    {
        $this->objectMap[$object] = $identifier;
        $this->identifierMap[$this->getClassIdentifier(get_class($object))][$identifier] = $object;
    }

    /**
     * Unregister an object
     *
     * @param object $object
     */
    public function unregisterObject($object)
    {
        unset($this->identifierMap[$this->getClassIdentifier(get_class($object))][$this->objectMap[$object]]);
        $this->objectMap->detach($object);
    }

    /**
     * Destroy the state of the persistence session and reset
     * all internal data.
     */
    public function destroy()
    {
        $this->identifierMap = [];
        $this->objectMap = new ObjectStorage();
        $this->reconstitutedEntities = new ObjectStorage();
    }

    /**
     * Objects are stored in the cache with their implementation class name
     * to allow reusing instances of different classes that point to the same implementation
     *
     * @param string $className
     * @return string a unique class identifier respecting configured implementation class names
     */
    protected function getClassIdentifier($className): string
    {
        return strtolower($this->objectContainer->getImplementationClassName($className));
    }
}
