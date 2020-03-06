<?php
namespace TYPO3\CMS\Extbase\Property\TypeConverter;

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

/**
 * This converter transforms arrays to simple objects (POPO) by setting properties.
 */
class ObjectConverter extends AbstractTypeConverter
{
    /**
     * @var int
     */
    const CONFIGURATION_TARGET_TYPE = 3;

    /**
     * @var int
     */
    const CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED = 4;

    /**
     * @var array
     */
    protected $sourceTypes = ['array'];

    /**
     * @var string
     */
    protected $targetType = 'object';

    /**
     * @var int
     */
    protected $priority = 10;

    /**
     * @var \TYPO3\CMS\Extbase\Object\Container\Container
     */
    protected $objectContainer;

    /**
     * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService
     */
    protected $reflectionService;

    /**
     * @param \TYPO3\CMS\Extbase\Object\Container\Container $objectContainer
     */
    public function injectObjectContainer(\TYPO3\CMS\Extbase\Object\Container\Container $objectContainer)
    {
        $this->objectContainer = $objectContainer;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Reflection\ReflectionService $reflectionService
     */
    public function injectReflectionService(\TYPO3\CMS\Extbase\Reflection\ReflectionService $reflectionService)
    {
        $this->reflectionService = $reflectionService;
    }

    /**
     * Only convert non-persistent types
     *
     * @param mixed $source
     * @param string $targetType
     * @return bool
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function canConvertFrom($source, $targetType)
    {
        return !is_subclass_of($targetType, \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject::class);
    }

    /**
     * Convert all properties in the source array
     *
     * @param mixed $source
     * @return array
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function getSourceChildPropertiesToBeConverted($source)
    {
        if (isset($source['__type'])) {
            unset($source['__type']);
        }
        return $source;
    }

    /**
     * The type of a property is determined by the reflection service.
     *
     * @param string $targetType
     * @param string $propertyName
     * @param \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration
     * @return string
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function getTypeOfChildProperty($targetType, $propertyName, \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration)
    {
        $configuredTargetType = $configuration->getConfigurationFor($propertyName)->getConfigurationValue(\TYPO3\CMS\Extbase\Property\TypeConverter\ObjectConverter::class, self::CONFIGURATION_TARGET_TYPE);
        if ($configuredTargetType !== null) {
            return $configuredTargetType;
        }

        $specificTargetType = $this->objectContainer->getImplementationClassName($targetType);
        $classSchema = $this->reflectionService->getClassSchema($specificTargetType);

        if ($classSchema->hasMethod(\TYPO3\CMS\Extbase\Reflection\ObjectAccess::buildSetterMethodName($propertyName))) {
            $methodParameters = $classSchema->getMethod(\TYPO3\CMS\Extbase\Reflection\ObjectAccess::buildSetterMethodName($propertyName))['params'] ?? [];
            $methodParameter = current($methodParameters);
            if (!isset($methodParameter['type'])) {
                throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException('Setter for property "' . $propertyName . '" had no type hint or documentation in target object of type "' . $specificTargetType . '".', 1303379158);
            }
            return $methodParameter['type'];
        }
        $method = $classSchema->getMethod('__construct');
        if (empty($method)) {
            $exceptionMessage = sprintf('Type of child property "%s" of class "%s" could not be '
                . 'derived from constructor arguments as said class does not have a constructor '
                . 'defined.', $propertyName, $specificTargetType);
            throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException($exceptionMessage, 1582385098);
        }

        $methodParameters = $classSchema->getMethod('__construct')['params'] ?? [];
        if (!isset($methodParameters[$propertyName])) {
            $exceptionMessage = sprintf('Type of child property "%1$s" of class "%2$s" could not be '
                . 'derived from constructor arguments as the constructor of said class does not '
                . 'have a parameter with property name "%1$s".', $propertyName, $specificTargetType);
            throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException($exceptionMessage, 1303379126);
        }

        $parameterType = $methodParameters[$propertyName]['type'];
        if ($parameterType === null) {
            $exceptionMessage = sprintf('Type of child property "%1$s" of class "%2$s" could not be '
                . 'derived from constructor argument "%1$s". This usually happens if the argument '
                . 'misses a type hint.', $propertyName, $specificTargetType);
            throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException($exceptionMessage, 1582385619);
        }

        return $parameterType;
    }

    /**
     * Convert an object from $source to an object.
     *
     * @param mixed $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration
     * @return object the target type
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidDataTypeException
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidPropertyMappingConfigurationException
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration = null)
    {
        $object = $this->buildObject($convertedChildProperties, $targetType);
        foreach ($convertedChildProperties as $propertyName => $propertyValue) {
            $result = \TYPO3\CMS\Extbase\Reflection\ObjectAccess::setProperty($object, $propertyName, $propertyValue);
            if ($result === false) {
                $exceptionMessage = sprintf(
                    'Property "%s" having a value of type "%s" could not be set in target object of type "%s". Make sure that the property is accessible properly, for example via an appropriate setter method.',
                    $propertyName,
                    (is_object($propertyValue) ? get_class($propertyValue) : gettype($propertyValue)),
                    $targetType
                );
                throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException($exceptionMessage, 1304538165);
            }
        }

        return $object;
    }

    /**
     * Determines the target type based on the source's (optional) __type key.
     *
     * @param mixed $source
     * @param string $originalTargetType
     * @param \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration
     * @return string
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidDataTypeException
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidPropertyMappingConfigurationException
     * @throws \InvalidArgumentException
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function getTargetTypeForSource($source, $originalTargetType, \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration = null)
    {
        $targetType = $originalTargetType;

        if (is_array($source) && array_key_exists('__type', $source)) {
            $targetType = $source['__type'];

            if ($configuration === null) {
                throw new \InvalidArgumentException('A property mapping configuration must be given, not NULL.', 1326277369);
            }
            if ($configuration->getConfigurationValue(\TYPO3\CMS\Extbase\Property\TypeConverter\ObjectConverter::class, self::CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED) !== true) {
                throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidPropertyMappingConfigurationException('Override of target type not allowed. To enable this, you need to set the PropertyMappingConfiguration Value "CONFIGURATION_OVERRIDE_TARGET_TYPE_ALLOWED" to TRUE.', 1317050430);
            }

            if ($targetType !== $originalTargetType && is_a($targetType, $originalTargetType, true) === false) {
                throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidDataTypeException('The given type "' . $targetType . '" is not a subtype of "' . $originalTargetType . '".', 1317048056);
            }
        }

        return $targetType;
    }

    /**
     * Builds a new instance of $objectType with the given $possibleConstructorArgumentValues. If
     * constructor argument values are missing from the given array the method
     * looks for a default value in the constructor signature. Furthermore, the constructor arguments are removed from $possibleConstructorArgumentValues
     *
     * @param array &$possibleConstructorArgumentValues
     * @param string $objectType
     * @return object The created instance
     * @throws \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException if a required constructor argument is missing
     */
    protected function buildObject(array &$possibleConstructorArgumentValues, $objectType)
    {
        $specificObjectType = $this->objectContainer->getImplementationClassName($objectType);
        $classSchema = $this->reflectionService->getClassSchema($specificObjectType);

        if ($classSchema->hasConstructor()) {
            $constructorSignature = $classSchema->getMethod('__construct')['params'] ?? [];
            $constructorArguments = [];
            foreach ($constructorSignature as $constructorArgumentName => $constructorArgumentInformation) {
                if (array_key_exists($constructorArgumentName, $possibleConstructorArgumentValues)) {
                    $constructorArguments[] = $possibleConstructorArgumentValues[$constructorArgumentName];
                    unset($possibleConstructorArgumentValues[$constructorArgumentName]);
                } elseif ($constructorArgumentInformation['optional'] === true) {
                    $constructorArguments[] = $constructorArgumentInformation['defaultValue'];
                } else {
                    throw new \TYPO3\CMS\Extbase\Property\Exception\InvalidTargetException('Missing constructor argument "' . $constructorArgumentName . '" for object of type "' . $objectType . '".', 1268734872);
                }
            }
            return call_user_func_array([$this->objectManager, 'get'], array_merge([$objectType], $constructorArguments));
        }
        return $this->objectManager->get($objectType);
    }
}
