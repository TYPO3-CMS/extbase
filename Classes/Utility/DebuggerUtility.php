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

namespace TYPO3\CMS\Extbase\Utility;

use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\AbstractValueObject;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\QueryObjectModelFactory;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * This class is a backport of the corresponding class of TYPO3 Flow.
 * All credits go to the TYPO3 Flow team.
 *
 * A debugging utility class
 */
class DebuggerUtility
{
    public const PLAINTEXT_INDENT = '   ';

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    protected static $renderedObjects;

    /**
     * Hardcoded list of Extbase class names (regex) which should not be displayed during debugging
     *
     * @var array
     */
    protected static $blacklistedClassNames = [
        ReflectionService::class,
        DataMapper::class,
        PersistenceManager::class,
        QueryObjectModelFactory::class,
        ContentObjectRenderer::class,
    ];

    /**
     * Hardcoded list of property names (regex) which should not be displayed during debugging
     *
     * @var array
     */
    protected static $blacklistedPropertyNames = ['warning'];

    /**
     * Is set to TRUE once the CSS file is included in the current page to prevent double inclusions of the CSS file.
     *
     * @var bool
     */
    protected static $stylesheetEchoed = false;

    /**
     * Defines the max recursion depth of the dump, set to 8 due to common memory limits
     *
     * @var int
     */
    protected static $maxDepth = 8;

    /**
     * Clear the state of the debugger
     */
    protected static function clearState(): void
    {
        self::$renderedObjects = new ObjectStorage();
    }

    /**
     * Renders a dump of the given value
     */
    protected static function renderDump($value, int $level, bool $plainText, bool $ansiColors, array $headerPrefix = []): array|callable|string
    {
        $dump = [];
        if (is_string($value)) {
            $croppedValue = mb_strlen($value) > 2000 ? mb_substr($value, 0, 2000) . '...' : $value;
            if ($plainText) {
                $dump = [
                    self::ansiEscapeWrap('"' . implode(PHP_EOL . str_repeat(self::PLAINTEXT_INDENT, $level + 1), mb_str_split($croppedValue, 76)) . '"', '33', $plainText, $ansiColors),
                    ' (',
                    mb_strlen($value),
                    ' chars)',
                ];
            } else {
                $lines = mb_str_split($croppedValue, 76);
                $content = [];
                foreach ($lines as $key => $line) {
                    if ($key > 0) {
                        $content[] = self::html('br', []);
                        $content[] = static fn(): string => '&nbsp;';
                    }
                    $content[] = $line;
                }

                $dump = self::html('span', ['class' => 'extbase-debug-string-container'], [
                    '\'',
                    self::html('span', ['class' => 'extbase-debug-string'], $content),
                    '\' (',
                    mb_strlen($value),
                    ' chars)',
                ]);
            }
        } elseif (is_numeric($value)) {
            $dump = [
                self::ansiEscapeWrap((string)$value, '35', $plainText, $ansiColors),
                ' (',
                gettype($value),
                ')',
            ];
        } elseif (is_bool($value)) {
            $dump = $value ? self::ansiEscapeWrap('TRUE', '32', $plainText, $ansiColors) : self::ansiEscapeWrap('FALSE', '32', $plainText, $ansiColors);
        } elseif ($value === null || is_resource($value)) {
            $dump = gettype($value);
        } elseif (is_array($value)) {
            return self::renderArray($value, $level + 1, $plainText, $ansiColors, $headerPrefix);
        } elseif (is_object($value)) {
            if ($value instanceof \Closure) {
                return self::renderClosure($value, $level + 1, $plainText, $ansiColors, $headerPrefix);
            }
            return self::renderObject($value, $level + 1, $plainText, $ansiColors, $headerPrefix);

        }
        if ($plainText) {
            return [$headerPrefix, $dump];
        }
        return self::html('div', ['class' => 'extbase-debug-header'], [$headerPrefix, $dump]);
    }

    /**
     * Renders a dump of the given array
     */
    protected static function renderArray(array $array, int $level, bool $plainText = false, bool $ansiColors = false, array $headerPrefix = []): array|callable
    {
        $content = [];
        $count = count($array);

        $header = $headerPrefix;
        $header[] = self::styled('', 'expander', $plainText, $ansiColors);
        $header[] = self::styled('array', 'type', $plainText, $ansiColors, 0, 1);
        $header[] = $count > 0 ? '(' . $count . ' item' . ($count > 1 ? 's' : '') . ')' : '(empty)';
        if ($level >= self::$maxDepth) {
            $header[] = self::styled('max depth', 'filtered', $plainText, $ansiColors, 1);
        } else {
            $content = self::renderCollection($array, $level, $plainText, $ansiColors);
        }

        if ($plainText) {
            return [...$header, ...$content];
        }

        if (array_filter($content) === []) {
            return self::html('div', ['class' => 'extbase-debug-header'], $header);
        }
        return self::html('details', ['class' => 'extbase-debugger-tree', 'open' => $level > 1 && $count > 0 ? null : ''], [
            self::html('summary', ['class' => 'extbase-debug-header'], $header),
            self::html('div', ['class' => 'extbase-debug-content'], $content),
        ]);
    }

    /**
     * Renders a dump of the given object
     */
    protected static function renderObject(object $object, int $level, bool $plainText = false, bool $ansiColors = false, array $headerPrefix = []): array|callable
    {
        if ($object instanceof LazyLoadingProxy) {
            $object = $object->_loadRealInstance();
            if (!is_object($object)) {
                return [...$headerPrefix, gettype($object)];
            }
        }
        $header = self::renderHeader($object, $level, $plainText, $ansiColors, $headerPrefix);
        $content = [];
        if ($level < self::$maxDepth && !self::isBlacklisted($object) && !(self::isAlreadyRendered($object) && $plainText !== true)) {
            $content = self::renderContent($object, $level, $plainText, $ansiColors);
        }
        if ($plainText) {
            return [...$header, ...$content];
        }

        if (array_filter($content) === []) {
            return self::html('div', ['class' => 'extbase-debugger-header'], $header);
        }
        return self::html('details', ['class' => 'extbase-debugger-tree', 'open' => $level > 1 ? null : ''], [
            self::html('summary', ['class' => 'extbase-debug-header', 'id' => spl_object_hash($object)], $header),
            self::html('div', ['class' => 'extbase-debug-content'], $content),
        ]);
    }

    /**
     * Renders a dump of the given closure
     */
    protected static function renderClosure(\Closure $object, int $level, bool $plainText = false, bool $ansiColors = false, array $headerPrefix = []): array|callable
    {
        $header = self::renderHeader($object, $level, $plainText, $ansiColors, $headerPrefix);
        $content = [];
        if ($level < self::$maxDepth && (!self::isAlreadyRendered($object) || $plainText)) {
            $content = self::renderContent($object, $level, $plainText, $ansiColors);
        }
        if ($plainText) {
            return [...$header, ...$content];
        }
        if (array_filter($content) === []) {
            return self::html('div', ['class' => 'extbase-debugger-header'], $header);
        }
        return self::html('details', ['class' => 'extbase-debugger-tree', 'open' => $level > 1 ? null : ''], [
            self::html('summary', ['class' => 'extbase-debug-header'], $header),
            self::html('div', ['class' => 'extbase-debug-content extbase-debug-closure'], $content),
        ]);
    }

    /**
     * Checks if a given object or property should be excluded/filtered
     *
     * @param object $value A ReflectionProperty or other Object
     * @return bool TRUE if the given object should be filtered
     */
    protected static function isBlacklisted(object $value): bool
    {
        if ($value instanceof \ReflectionProperty) {
            $result = in_array($value->getName(), self::$blacklistedPropertyNames, true);
        } else {
            $result = in_array(get_class($value), self::$blacklistedClassNames, true);
        }
        return $result;
    }

    /**
     * Checks if a given object was already rendered.
     *
     * @return bool TRUE if the given object was already rendered
     */
    protected static function isAlreadyRendered(object $object): bool
    {
        return self::$renderedObjects->contains($object);
    }

    /**
     * Renders the header of a given object/collection. It is usually the class name along with some flags.
     *
     * @return array<string|callable> string The rendered header with tags
     */
    protected static function renderHeader(object $object, int $level, bool $plainText, bool $ansiColors, array $headerPrefix): array
    {
        $dump = $headerPrefix;
        $persistenceType = null;
        $className = get_class($object);
        $classReflection = new \ReflectionClass($className);
        $dump[] = self::styled('', 'expander', $plainText, $ansiColors);
        $dump[] = self::styled($className, 'type', $plainText, $ansiColors);

        if (!$object instanceof \Closure) {
            if ($object instanceof SingletonInterface) {
                $scope = 'singleton';
            } else {
                $scope = 'prototype';
            }
            $dump[] = self::styled($scope, 'scope', $plainText, $ansiColors, 1);
            if ($object instanceof DomainObjectInterface) {
                if ($object->_isDirty()) {
                    $persistenceType = 'modified';
                } elseif ($object->_isNew()) {
                    $persistenceType = 'transient';
                } else {
                    $persistenceType = 'persistent';
                }
            }
            if ($object instanceof ObjectStorage && $object->_isDirty()) {
                $persistenceType = 'modified';
            }
            if ($object instanceof AbstractEntity) {
                $domainObjectType = 'entity';
            } elseif ($object instanceof AbstractValueObject) {
                $domainObjectType = 'valueobject';
            } else {
                $domainObjectType = 'object';
            }
            $persistenceType = $persistenceType === null ? '' : $persistenceType . ' ';
            $dump[] = self::styled($persistenceType . $domainObjectType, 'ptype', $plainText, $ansiColors, 1);
        }

        if (strpos(implode('|', self::$blacklistedClassNames), get_class($object)) > 0) {
            $dump[] = self::styled('filtered', 'filtered', $plainText, $ansiColors, 1);
        } elseif (self::$renderedObjects->contains($object) && !$plainText) {
            $dump = [
                self::html('a', ['href' => '#' . spl_object_hash($object), 'class' => 'extbase-debug-seeabove'], [
                    $dump,
                    self::html('span', ['class' => 'extbase-debug-filtered'], 'see above'),
                ]),
            ];
        } elseif ($level >= self::$maxDepth && !$object instanceof \DateTimeInterface) {
            $dump[] = self::styled('max depth', 'filtered', $plainText, $ansiColors, 1);
        }

        if ($object instanceof \Countable) {
            $objectCount = count($object);
            $dump[] = $objectCount > 0 ? ' (' . $objectCount . ' items)' : ' (empty)';
        }
        if ($object instanceof \DateTimeInterface) {
            $dump[] = ' (' . $object->format(\DateTimeInterface::RFC3339) . ', ' . $object->getTimestamp() . ')';
        }
        if ($object instanceof DomainObjectInterface && !$object->_isNew()) {
            $dump[] = ' (uid=' . $object->getUid() . ', pid=' . $object->getPid() . ')';
        }

        return $dump;
    }

    protected static function renderContent(object $object, int $level, bool $plainText, bool $ansiColors): string|array
    {
        $dump = [];
        if ($object instanceof \Iterator || $object instanceof \ArrayObject) {
            $dump[] = self::renderCollection($object, $level, $plainText, $ansiColors);
        } else {
            self::$renderedObjects->attach($object);
            if ($object instanceof \Closure) {
                if ($plainText) {
                    $dump[] = PHP_EOL;
                }
                $dump[] = str_repeat(self::PLAINTEXT_INDENT, $level);
                $dump[] = self::styled('function (', 'closure', $plainText, $ansiColors);

                $reflectionFunction = new \ReflectionFunction($object);
                $params = [];
                $count = 0;
                foreach ($reflectionFunction->getParameters() as $parameter) {
                    if (++$count > 1) {
                        $params[] = ', ';
                    }
                    $isFirst = false;
                    $type = $parameter->getType();
                    // @todo    Following code adds for parameter of type array or a class the classname or array
                    //          to the output. All other introduced possible parameter types are not respected yet.
                    //          This should be extended, and also respect possible type combinations like
                    //          union types and union intersect types.
                    if ($type instanceof \ReflectionNamedType && $type->isBuiltin() && $type->getName() === 'array') {
                        $params[] = self::styled('array', 'type', $plainText, $ansiColors, 0, 1);
                    } elseif ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && !empty($type->getName())) {
                        $params[] = self::styled($type->getName(), 'type', $plainText, $ansiColors, 0, 1);
                    }
                    if ($parameter->isPassedByReference()) {
                        $params[] = '&';
                    }
                    if ($parameter->isVariadic()) {
                        $params[] = '...';
                    }
                    $params[] = self::styled('$' . $parameter->name, 'property', $plainText, $ansiColors);
                    if ($parameter->isDefaultValueAvailable()) {
                        $params[] = ' = ';
                        $params[] = self::styled(var_export($parameter->getDefaultValue(), true), 'string', $plainText, $ansiColors);
                    }
                }
                $dump = [...$dump, ...$params];
                $dump[] = self::styled(') {', 'closure', $plainText, $ansiColors);
                $dump[] = PHP_EOL;

                $lines = (array)file((string)$reflectionFunction->getFileName());
                for ($l = (int)$reflectionFunction->getStartLine(); $l < (int)$reflectionFunction->getEndLine() - 1; ++$l) {
                    $line = (string)($lines[$l] ?? '');
                    $dump[] = $line;
                }
                $dump[] = str_repeat(self::PLAINTEXT_INDENT, $level);
                $dump[] = self::styled('}', 'closure', $plainText, $ansiColors);
                if ($plainText) {
                    $dump[] = PHP_EOL;
                }
            } else {
                if (get_class($object) === \stdClass::class) {
                    $objReflection = new \ReflectionObject($object);
                    $properties = $objReflection->getProperties();
                } else {
                    $classReflection = new \ReflectionClass(get_class($object));
                    $properties = $classReflection->getProperties();
                }
                foreach ($properties as $property) {
                    if (self::isBlacklisted($property)) {
                        continue;
                    }
                    $visibility = ($property->isProtected() ? 'protected' : ($property->isPrivate() ? 'private' : 'public'));
                    $header = [
                        PHP_EOL . str_repeat(self::PLAINTEXT_INDENT, $level),
                        self::styled($property->getName(), 'property', $plainText, $ansiColors),
                        ' => ',
                        self::styled($visibility, 'visibility', $plainText, $ansiColors, 0, 1),
                    ];
                    if (!$property->isInitialized($object)) {
                        $header[] = self::styled('uninitialized', 'uninitialized', $plainText, $ansiColors, 0, 1);
                        if ($plainText) {
                            $dump[] = $header;
                        } else {
                            $dump[] = self::html('div', ['class' => 'extbase-debug-header'], $header);
                        }
                        continue;
                    }
                    if ($object instanceof DomainObjectInterface && !$object->_isNew() && $object->_isDirty($property->getName())) {
                        $header[] = self::styled('modified', 'dirty', $plainText, $ansiColors, 1);
                    }
                    $dump[] = self::renderDump($property->getValue($object), $level, $plainText, $ansiColors, $header);
                }
            }
        }
        return $dump;
    }

    protected static function renderCollection(iterable $collection, int $level, bool $plainText, bool $ansiColors): array
    {
        $dump = [];
        foreach ($collection as $key => $value) {
            // Note: Due to the TYPO3\CMS\Core\Type\Map implementation, the key can also be an object.
            $key = is_object($key) ? get_class($key) : (string)$key;

            $dump = [
                ...$dump,
                self::renderDump($value, $level, $plainText, $ansiColors, [
                    PHP_EOL . str_repeat(self::PLAINTEXT_INDENT, $level),
                    self::styled($key, 'property', $plainText, $ansiColors),
                    ' => ',
                ]),
            ];
        }
        if ($collection instanceof \Iterator && !$collection instanceof \Generator) {
            $collection->rewind();
        }
        return $dump;
    }

    /**
     * Wrap a string with the ANSI escape sequence for colorful output
     *
     * @param string $string The string to wrap
     * @param string $ansiColorSequence The ansi color sequence (e.g. "1;37")
     * @param bool $plainText If FALSE, the string will be HTML encoded
     * @param bool $ansiColors If TRUE, the string will have console colors applied
     * @return callable The wrapped or raw string
     */
    protected static function ansiEscapeWrap(string $string, string $ansiColorSequence, bool $plainText, bool $ansiColors): callable
    {
        if ($plainText && $ansiColors) {
            return static fn(): string => '[' . $ansiColorSequence . 'm' . self::escapeConsoleText($string) . '[0m';
        }
        if ($plainText) {
            return static fn(): string => self::escapeConsoleText($string);
        }
        return static fn(): string => self::escapeHtml($string);
    }

    /**
     * A var_dump function optimized for Extbase's object structures
     *
     * @param mixed $variable The value to dump
     * @param string $title optional custom title for the debug output
     * @param int $maxDepth Sets the max recursion depth of the dump. De- or increase the number according to your needs and memory limit.
     * @param bool $plainText If TRUE, the dump is in plain text, if FALSE the debug output is in HTML format.
     * @param bool $ansiColors If TRUE (default), ANSI color codes is added to the output, if FALSE the debug output not colored.
     * @param bool $return if TRUE, the dump is returned for custom post-processing (e.g. embed in custom HTML). If FALSE (default), the dump is directly displayed.
     * @param array $blacklistedClassNames An array of class names (RegEx) to be filtered. Default is an array of some common class names.
     * @param array $blacklistedPropertyNames An array of property names and/or array keys (RegEx) to be filtered. Default is an array of some common property names.
     * @return string if $return is TRUE, the dump is returned. By default, the dump is directly displayed, and nothing is returned.
     */
    public static function var_dump(
        $variable,
        ?string $title = null,
        int $maxDepth = 8,
        bool $plainText = false,
        bool $ansiColors = true,
        bool $return = false,
        ?array $blacklistedClassNames = null,
        ?array $blacklistedPropertyNames = null
    ): string {
        self::$maxDepth = $maxDepth;
        if ($title === null) {
            $title = 'Extbase Variable Dump';
        }
        $ansiColors = $plainText && $ansiColors;
        if ($ansiColors === true) {
            $title = '[1m' . $title . '[0m';
        }
        $backupBlacklistedClassNames = self::$blacklistedClassNames;
        if (is_array($blacklistedClassNames)) {
            self::$blacklistedClassNames = $blacklistedClassNames;
        }
        $backupBlacklistedPropertyNames = self::$blacklistedPropertyNames;
        if (is_array($blacklistedPropertyNames)) {
            self::$blacklistedPropertyNames = $blacklistedPropertyNames;
        }
        self::clearState();

        $css = self::cssTreeToString([
            '.extbase-debugger-tree' => [
                'position' => 'relative',
            ],
            '.extbase-debugger-tree summary' => [
                'list-style' => 'none',
                'cursor' => 'pointer',
                'white-space' => 'nowrap',
            ],
            '.extbase-debugger-tree:has(>summary:target)' => [
                'outline' => 'var(--typo3-debugger-outline, #101010) auto 1px',
                'padding' => '3px',
            ],
            '.extbase-debugger-tree :is(.extbase-debug-header)>*' => [
                'vertical-align' => 'top',
            ],
            '.extbase-debugger-tree .extbase-debug-expander' => [
                'position' => 'relative',
                'display' => 'none',
                'height' => '1em',
                'aspect-ratio' => '1',
                'margin' => '0 3px 0 0',
                'vertical-align' => '-12%',
                'cursor' => 'pointer',
            ],
            '.extbase-debugger-tree summary>.extbase-debug-expander' => [
                'display' => 'inline-block',
            ],
            // Hide expander on first level
            '.extbase-debugger-inner>details>summary>.extbase-debug-expander' => [
                'display' => 'none',
            ],
            '.extbase-debugger-tree .extbase-debug-expander::before' => [
                'content' => '""',
                'position' => 'absolute',
                'inset' => '0',
                'background-size' => '100%',
                'display' => 'inline-block',
                'background-image' => sprintf('url(data:image/svg+xml;base64,%s)', base64_encode(
                    '<?xml version="1.0" encoding="utf-8"?><svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" fill="#888"><path d="M11,11H0V0h11V11z M10,1H1v9h9V1z"/><rect x="2" y="5" width="7" height="1"/><rect x="5" y="2" width="1" height="7"/></svg>'
                )),
            ],
            '.extbase-debugger-tree[open]>summary>.extbase-debug-expander::before' => [
                'background-image' => sprintf('url(data:image/svg+xml;base64,%s)', base64_encode(
                    '<?xml version="1.0" encoding="utf-8"?><svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" fill="#888"><path d="M11,11H0V0h11V11z M10,1H1v9h9V1z"/><rect x="2" y="5" width="7" height="1"/></svg>'
                )),
            ],
            '.extbase-debugger-tree .extbase-debug-content' => [
                'padding-left' => '3ch',
            ],
            '.extbase-debugger' => [
                'display' => 'block',
                'text-align' => 'left',
                'background' => 'var(--typo3-debugger-bg, #2a2a2a)',
                'border' => '1px solid var(--typo3-debugger-border-color, #2a2a2a)',
                'box-shadow' => 'var(--typo3-debugger-box-shadow, 0 3px 0 rgba(0, 0, 0, .5))',
                'margin' => '20px',
                'overflow' => 'hidden',
                'border-radius' => 'var(--typo3-debugger-border-radius, 4px)',
            ],
            '.extbase-debugger-floating' => [
                'position' => 'relative',
                'z-index' => '99990',
            ],
            '.extbase-debugger-top' => [
                'background' => 'var(--typo3-debugger-top-bg, #444)',
                'font-size' => '12px',
                'font-family' => 'monospace',
                'color' => 'var(--typo3-debugger-top-color, #f1f1f1)',
                'padding' => '6px 15px',
            ],
            '.extbase-debugger-inner' => [
                'overflow-x' => 'auto',
            ],
            '.extbase-debugger-center' => [
                'padding' => '0 15px',
                'margin' => '15px 0',
                'background-image' => sprintf(
                    'repeating-linear-gradient(to bottom, transparent 0, transparent 20px, %1$s 20px, %1$s 40px)',
                    'var(--typo3-debugger-bg-variant, #252525)',
                ),
                'color' => 'var(--typo3-debugger-color-variant, #999)',
                'word-wrap' => 'break-word',
            ],
            '.extbase-debugger-center, .extbase-debugger-center :is(.extbase-debug-string, a, p, pre, strong)' => [
                'font-size' => '12px',
                'font-weight' => '400',
                'font-family' => 'monospace',
                'line-height' => '20px',
                'color' => 'var(--typo3-debugger-color, #f1f1f1)',
            ],
            '.extbase-debugger-center .extbase-debug-string-container' => [
                'display' => 'inline-block',
            ],
            '.extbase-debugger-center :is(.extbase-debug-filtered, .extbase-debug-proxy, .extbase-debug-ptype, .extbase-debug-visibility, .extbase-debug-uninitialized, .extbase-debug-scope, .extbase-debug-dirty)' => [
                'color' => '#fff',
                'font-size' => '10px',
                'line-height' => '18px',
                'padding' => '2px 4px',
                'margin-right' => '2px',
            ],
            '.extbase-debugger-center .extbase-debug-unregistered' => [
                'background-color' => 'var(--typo3-debugger-unregistered-bg, #dce1e8)',
            ],
            '.extbase-debugger-center .extbase-debug-scope' => [
                'background-color' => 'var(--typo3-debugger-scope-bg, #497AA2)',
            ],
            '.extbase-debugger-center .extbase-debug-ptype' => [
                'background-color' => 'var(--typo3-debugger-ptype-bg, #698747)',
            ],
            '.extbase-debugger-center .extbase-debug-visibility' => [
                'background-color' => 'var(--typo3-debugger-visibility-bg, #6c0787)',
            ],
            '.extbase-debugger-center .extbase-debug-uninitialized' => [
                'background-color' => 'var(--typo3-debugger-uninitializedy-bg, #698747)',
            ],
            '.extbase-debugger-center .extbase-debug-dirty' => [
                'background-color' => 'var(--typo3-debugger-dirty-bg, #664d00)',
            ],
            '.extbase-debugger-center .extbase-debug-filtered' => [
                'background-color' => 'var(--typo3-debugger-filtered-bg, #664d00)',
            ],
            '.extbase-debugger-center .extbase-debug-string' => [
                'color' => 'var(--typo3-debugger-string-color, #ce9178)',
                'white-space' => 'normal',
            ],
            '.extbase-debugger-center .extbase-debug-type' => [
                'color' => 'var(--typo3-debugger-type-color, #569CD6)',
                'padding-right' => '4px',
            ],
            '.extbase-debugger-center .extbase-debug-closure' => [
                'color' => 'var(--typo3-debugger-closure-color, #9BA223)',
                'white-space' => 'pre',
            ],
            '.extbase-debugger-center .extbase-debug-property' => [
                'color' => 'var(--typo3-debugger-property-color, #f1f1f1)',
            ],
            '.extbase-debugger-center .extbase-debug-seeabove' => [
                'display' => 'block',
                'text-decoration' => 'none',
                'font-style' => 'italic',
            ],
        ]);

        $style = '';
        if (!$plainText && self::$stylesheetEchoed === false) {
            $style = self::html('style', ['nonce' => self::resolveNonceValue()], static fn(): string => $css)();
            self::$stylesheetEchoed = true;
        }
        if ($plainText) {
            $output = $title . self::render(
                [
                    PHP_EOL,
                    self::renderDump($variable, 0, true, $ansiColors),
                    PHP_EOL,
                    PHP_EOL,
                ],
                self::escapeConsoleText(...)
            );

        } else {
            $output = self::html('div', ['class' => 'extbase-debugger ' . ($return ? 'extbase-debugger-inline' : 'extbase-debugger-floating')], [
                self::html('div', ['class' => 'extbase-debugger-top'], $title),
                self::html('div', ['class' => 'extbase-debugger-center'], [
                    self::html('div', ['class' => 'extbase-debugger-inner'], [
                        self::renderDump($variable, 0, false, false),
                    ]),
                ]),
            ])();
        }
        self::$blacklistedClassNames = $backupBlacklistedClassNames;
        self::$blacklistedPropertyNames = $backupBlacklistedPropertyNames;
        if ($return === true) {
            return $style . $output;
        }
        echo $style . $output;

        return '';
    }

    protected static function resolveNonceValue(): string
    {
        return GeneralUtility::makeInstance(RequestId::class)->nonce->consume();
    }

    protected static function styled(
        string $content,
        string $style,
        bool $plainText,
        bool $ansiColors,
        int $spaceBefore = 0,
        int $spaceAfter = 0,
    ): callable|string {
        $styleMap = [
            'expander' => '',
            'string' => '33',
            'closure' => '33',
            'type' => '36',
            'property' => '37',
            'ptype' => '42;30',
            'visibility' => '42;30',
            'dirty' => '43;30',
            'scope' => '44;37',
            'filtered' => '47;30',
            'uninitialized' => '45;37',
        ];
        if (!isset($styleMap[$style])) {
            throw new \InvalidArgumentException('Invalid debugger style: ' . $style, 1726659808);
        }

        if ($plainText) {
            if ($style === 'expander') {
                return '';
            }
            return static fn() => [
                str_repeat(' ', $spaceBefore),
                self::ansiEscapeWrap($content, $styleMap[$style], $plainText, $ansiColors),
                str_repeat(' ', $spaceAfter),
            ];
        }

        return self::html('span', ['class' => 'extbase-debug-' . $style], $content);
    }

    protected static function html(string $tagName, array $attributes, string|array|callable|null $content = null): callable
    {
        if ($tagName === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $tagName)) {
            throw new \InvalidArgumentException('Invalid tag name', 1726659807);
        }
        return static fn(): string => implode('', [
            '<',
            $tagName,
            count($attributes) > 0 ? ' ' : '',
            // filter null attributes
            GeneralUtility::implodeAttributes(array_filter($attributes, static fn(?string $value): bool => $value !== null), true, true),
            '>',
            ...($content === null ? [] : [
                self::render($content, self::escapeHtml(...)),
                '</',
                $tagName,
                '>',
            ]),
        ]);
    }

    protected static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_HTML5, 'UTF-8');
    }

    protected static function escapeConsoleText(string $text): string
    {
        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/u',
            static fn(array $matches): string => $matches[0] === PHP_EOL ? PHP_EOL : '\\x' . str_pad(dechex(ord($matches[0])), 2, '0', STR_PAD_LEFT),
            $text
        ) ?? $text;
    }

    protected static function render(string|callable|array $content, callable $escape): string
    {
        if (is_string($content)) {
            return $escape($content);
        }
        if ($content instanceof \Closure) {
            $content = $content();
            if (is_string($content)) {
                return $content;
            }
        }
        if (is_array($content)) {
            return implode('', array_map(static fn(string|callable|array $content): string => self::render($content, $escape), $content));
        }

        throw new \InvalidArgumentException('Invalid callable return type: ' . gettype($content), 1726673500);
    }

    /**
     * Converts a CSS tree to a CSS stylesheet string
     *
     * Example input:
     *
     *     [
     *         '.my-class' => [
     *             'display' => 'block',
     *             'color' => 'black',
     *         ],
     *         '.other-class' => [
     *             'display' => 'flex',
     *         ],
     *     ]
     *
     * Output:
     *     .my-class{display:block;color:black}
     *     .other-class{display:flex}
     *
     * @param array<string, array<string, string>> $cssTree
     */
    protected static function cssTreeToString(array $cssTree): string
    {
        $rules = array_map(
            static fn(string $selector): string => sprintf(
                '%s{%s}',
                $selector,
                implode(
                    ';',
                    array_map(
                        static fn(string $property): string => sprintf(
                            '%s:%s',
                            $property,
                            $cssTree[$selector][$property]
                        ),
                        array_keys($cssTree[$selector])
                    )
                )
            ),
            array_keys($cssTree)
        );
        $stylesheet = implode(PHP_EOL, $rules);
        // Optimize away uneeded whitespace
        return str_replace(', ', ',', $stylesheet);
    }
}
