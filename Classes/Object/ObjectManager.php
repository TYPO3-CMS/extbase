<?php
namespace TYPO3\CMS\Extbase\Object;

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

use TYPO3\CMS\Extbase\Object\Container\Container;

/**
 * Implementation of the default Extbase Object Manager
 *
 * @template T
 */
class ObjectManager implements ObjectManagerInterface
{
    /**
     * @var \TYPO3\CMS\Extbase\Object\Container\Container
     */
    protected $objectContainer;

    /**
     * Constructs a new Object Manager
     */
    public function __construct()
    {
        $this->objectContainer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class);
    }

    /**
     * Serialization (sleep) helper.
     *
     * Removes properties of this object from serialization.
     * This action is necessary, since there might be closures used
     * in the accordant content objects (e.g. in FLUIDTEMPLATE) which
     * cannot be serialized. It's fine to reset $this->contentObjects
     * since elements will be recreated and are just a local cache,
     * but not required for runtime logic and behaviour.
     *
     * @see http://forge.typo3.org/issues/36820
     * @return array Names of the properties to be serialized
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function __sleep()
    {
        // Use get_objects_vars() instead of
        // a much more expensive Reflection:
        $properties = get_object_vars($this);
        unset($properties['objectContainer']);
        return array_keys($properties);
    }

    /**
     * Unserialization (wakeup) helper.
     *
     * Initializes the properties again that have been removed by
     * a call to the __sleep() method on serialization before.
     *
     * @see http://forge.typo3.org/issues/36820
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function __wakeup()
    {
        $this->__construct();
    }

    /**
     * Returns TRUE if an object with the given name is registered
     *
     * @param string $objectName Name of the object
     * @return bool TRUE if the object has been registered, otherwise FALSE
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function isRegistered($objectName)
    {
        return class_exists($objectName, true);
    }

    /**
     * Returns a fresh or existing instance of the object specified by $objectName.
     *
     * @param string|class-string<T> $objectName The name of the object to return an instance of
     * @param array $constructorArguments
     * @return object&T The object instance
     */
    public function get($objectName, ...$constructorArguments)
    {
        if ($objectName === 'DateTime') {
            $instance = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($objectName, ...$constructorArguments);
        } else {
            $instance = $this->objectContainer->getInstance($objectName, $constructorArguments);
        }
        return $instance;
    }

    /**
     * Returns the scope of the specified object.
     *
     * @param string $objectName The object name
     * @return int One of the Container::SCOPE_ constants
     * @throws \TYPO3\CMS\Extbase\Object\Container\Exception\UnknownObjectException
     */
    public function getScope($objectName)
    {
        if (!$this->isRegistered($objectName)) {
            throw new \TYPO3\CMS\Extbase\Object\Container\Exception\UnknownObjectException('Object "' . $objectName . '" is not registered.', 1265367590);
        }
        return $this->objectContainer->isSingleton($objectName) ? Container::SCOPE_SINGLETON : Container::SCOPE_PROTOTYPE;
    }

    /**
     * Create an instance of $className without calling its constructor
     *
     * @param string|class-string<T> $className
     * @return object&T
     */
    public function getEmptyObject($className)
    {
        return $this->objectContainer->getEmptyObject($className);
    }
}
