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

namespace TYPO3\CMS\Extbase\Mvc\Web;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Exception as MvcException;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidActionNameException;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidControllerNameException;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Extbase\Service\ExtensionService;

/**
 * Builds a web request.
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class RequestBuilder implements SingletonInterface
{
    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * This is a unique key for a plugin (not the extension key!)
     *
     * @var string
     */
    protected $pluginName = 'plugin';

    /**
     * The name of the extension (in UpperCamelCase)
     *
     * @var string
     */
    protected $extensionName;

    /**
     * The class name of the default controller
     *
     * @var string
     */
    private $defaultControllerClassName;

    /**
     * The default controller name
     *
     * @var string
     */
    protected $defaultControllerName = '';

    /**
     * The default format of the response object
     *
     * @var string
     */
    protected $defaultFormat = 'html';

    /**
     * The allowed actions of the controller. This actions can be called via $_GET and $_POST.
     *
     * @var array
     */
    protected $allowedControllerActions = [];

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @var \TYPO3\CMS\Extbase\Service\ExtensionService
     */
    protected $extensionService;

    /**
     * @var \TYPO3\CMS\Extbase\Service\EnvironmentService
     */
    protected $environmentService;

    /**
     * @var array
     */
    private $controllerAliasToClassMapping = [];

    /**
     * @var array
     */
    private $controllerClassToAliasMapping = [];

    /**
     * @var array|string[]
     */
    private $allowedControllerAliases = [];

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     */
    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\ExtensionService $extensionService
     */
    public function injectExtensionService(ExtensionService $extensionService)
    {
        $this->extensionService = $extensionService;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\EnvironmentService $environmentService
     */
    public function injectEnvironmentService(EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    /**
     * @throws MvcException
     * @see \TYPO3\CMS\Extbase\Core\Bootstrap::initializeConfiguration
     */
    protected function loadDefaultValues()
    {
        // todo: See comment in \TYPO3\CMS\Extbase\Core\Bootstrap::initializeConfiguration for further explanation
        // todo: on why we shouldn't use the configuration manager here.
        $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        if (empty($configuration['extensionName'])) {
            throw new MvcException('"extensionName" is not properly configured. Request can\'t be dispatched!', 1289843275);
        }
        if (empty($configuration['pluginName'])) {
            throw new MvcException('"pluginName" is not properly configured. Request can\'t be dispatched!', 1289843277);
        }
        $this->extensionName = $configuration['extensionName'];
        $this->pluginName = $configuration['pluginName'];
        $defaultControllerConfiguration = reset($configuration['controllerConfiguration']) ?? [];
        $this->defaultControllerClassName = $defaultControllerConfiguration['className'] ?? null;
        $this->defaultControllerName = $defaultControllerConfiguration['alias'] ?? null;
        $this->allowedControllerActions = [];
        foreach ($configuration['controllerConfiguration'] as $controllerClassName => $controllerConfiguration) {
            $this->allowedControllerActions[$controllerClassName] = $controllerConfiguration['actions'] ?? null;
            $this->controllerAliasToClassMapping[$controllerConfiguration['alias']] = $controllerConfiguration['className'];
            $this->controllerClassToAliasMapping[$controllerConfiguration['className']] = $controllerConfiguration['alias'];
            $this->allowedControllerAliases[] = $controllerConfiguration['alias'];
        }
        if (!empty($configuration['format'])) {
            $this->defaultFormat = $configuration['format'];
        }
    }

    /**
     * Builds a web request object from the raw HTTP information and the configuration
     *
     * @return \TYPO3\CMS\Extbase\Mvc\Web\Request The web request as an object
     */
    public function build()
    {
        $this->loadDefaultValues();
        $pluginNamespace = $this->extensionService->getPluginNamespace($this->extensionName, $this->pluginName);
        /** @var \TYPO3\CMS\Core\Http\ServerRequest $typo3Request */
        $typo3Request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($typo3Request instanceof ServerRequestInterface) {
            $queryArguments = $typo3Request->getAttribute('routing');
            if ($queryArguments instanceof PageArguments) {
                $getParameters = $queryArguments->get($pluginNamespace) ?? [];
            } else {
                $getParameters = $typo3Request->getQueryParams()[$pluginNamespace] ?? [];
            }
            $bodyParameters = $typo3Request->getParsedBody()[$pluginNamespace] ?? [];
            $parameters = $getParameters;
            ArrayUtility::mergeRecursiveWithOverrule($parameters, $bodyParameters);
        } else {
            $parameters = GeneralUtility::_GPmerged($pluginNamespace);
        }

        $files = $this->untangleFilesArray($_FILES);
        if (is_array($files[$pluginNamespace] ?? null)) {
            $parameters = array_replace_recursive($parameters, $files[$pluginNamespace]);
        }

        $controllerClassName = $this->resolveControllerClassName($parameters);
        $actionName = $this->resolveActionName($controllerClassName, $parameters);

        $baseUri = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        if ($this->environmentService->isEnvironmentInBackendMode()) {
            $baseUri .= TYPO3_mainDir;
        }

        /** @var \TYPO3\CMS\Extbase\Mvc\Web\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName($this->pluginName);
        $request->setControllerExtensionName($this->extensionName);
        $request->setControllerAliasToClassNameMapping($this->controllerAliasToClassMapping);
        $request->setControllerName($this->controllerClassToAliasMapping[$controllerClassName]);
        $request->setControllerActionName($actionName);
        // @todo Use Environment
        $request->setRequestUri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseUri($baseUri);
        $request->setMethod($this->getServerRequestMethod($typo3Request));
        if (isset($parameters['format']) && is_string($parameters['format']) && $parameters['format'] !== '') {
            $request->setFormat(filter_var($parameters['format'], FILTER_SANITIZE_STRING));
        } else {
            $request->setFormat($this->defaultFormat);
        }
        foreach ($parameters as $argumentName => $argumentValue) {
            $request->setArgument($argumentName, $argumentValue);
        }
        return $request;
    }

    /**
     * Returns the current ControllerName extracted from given $parameters.
     * If no controller is specified, the defaultControllerName will be returned.
     * If that's not available, an exception is thrown.
     *
     * @param array $parameters
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidControllerNameException
     * @throws MvcException if the controller could not be resolved
     * @throws \TYPO3\CMS\Core\Error\Http\PageNotFoundException
     * @return string
     */
    protected function resolveControllerClassName(array $parameters)
    {
        if (!isset($parameters['controller']) || $parameters['controller'] === '') {
            if (empty($this->defaultControllerClassName)) {
                throw new MvcException('The default controller for extension "' . $this->extensionName . '" and plugin "' . $this->pluginName . '" can not be determined. Please check for TYPO3\\CMS\\Extbase\\Utility\\ExtensionUtility::configurePlugin() in your ext_localconf.php.', 1316104317);
            }
            return $this->defaultControllerClassName;
        }
        $controllerClassName = $this->controllerAliasToClassMapping[$parameters['controller']] ?? '';
        if (!in_array($controllerClassName, array_keys($this->allowedControllerActions))) {
            $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
            if (isset($configuration['mvc']['throwPageNotFoundExceptionIfActionCantBeResolved']) && (bool)$configuration['mvc']['throwPageNotFoundExceptionIfActionCantBeResolved']) {
                throw new PageNotFoundException('The requested resource was not found', 1313857897);
            }
            if (isset($configuration['mvc']['callDefaultActionIfActionCantBeResolved']) && (bool)$configuration['mvc']['callDefaultActionIfActionCantBeResolved']) {
                return $this->defaultControllerClassName;
            }
            throw new InvalidControllerNameException(
                'The controller "' . $parameters['controller'] . '" is not allowed by plugin "' . $this->pluginName . '". Please check for TYPO3\\CMS\\Extbase\\Utility\\ExtensionUtility::configurePlugin() in your ext_localconf.php.',
                1313855173
            );
        }
        return filter_var($controllerClassName, FILTER_SANITIZE_STRING);
    }

    /**
     * Returns the current actionName extracted from given $parameters.
     * If no action is specified, the defaultActionName will be returned.
     * If that's not available or the specified action is not defined in the current plugin, an exception is thrown.
     *
     * @param string $controllerClassName
     * @param array $parameters
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidActionNameException
     * @throws MvcException
     * @throws \TYPO3\CMS\Core\Error\Http\PageNotFoundException
     * @return string
     */
    protected function resolveActionName($controllerClassName, array $parameters)
    {
        $defaultActionName = is_array($this->allowedControllerActions[$controllerClassName]) ? current($this->allowedControllerActions[$controllerClassName]) : '';
        if (!isset($parameters['action']) || $parameters['action'] === '') {
            if ($defaultActionName === '') {
                throw new MvcException('The default action can not be determined for controller "' . $controllerClassName . '". Please check TYPO3\\CMS\\Extbase\\Utility\\ExtensionUtility::configurePlugin() in your ext_localconf.php.', 1295479651);
            }
            return $defaultActionName;
        }
        $actionName = $parameters['action'];
        $allowedActionNames = $this->allowedControllerActions[$controllerClassName];
        if (!in_array($actionName, $allowedActionNames)) {
            $configuration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
            if (isset($configuration['mvc']['throwPageNotFoundExceptionIfActionCantBeResolved']) && (bool)$configuration['mvc']['throwPageNotFoundExceptionIfActionCantBeResolved']) {
                throw new PageNotFoundException('The requested resource was not found', 1313857898);
            }
            if (isset($configuration['mvc']['callDefaultActionIfActionCantBeResolved']) && (bool)$configuration['mvc']['callDefaultActionIfActionCantBeResolved']) {
                return $defaultActionName;
            }
            throw new InvalidActionNameException('The action "' . $actionName . '" (controller "' . $controllerClassName . '") is not allowed by this plugin / module. Please check TYPO3\\CMS\\Extbase\\Utility\\ExtensionUtility::configurePlugin() in your ext_localconf.php / TYPO3\\CMS\\Extbase\\Utility\\ExtensionUtility::configureModule() in your ext_tables.php.', 1313855175);
        }
        return filter_var($actionName, FILTER_SANITIZE_STRING);
    }

    /**
     * Transforms the convoluted _FILES superglobal into a manageable form.
     *
     * @param array $convolutedFiles The _FILES superglobal
     * @return array Untangled files
     */
    protected function untangleFilesArray(array $convolutedFiles)
    {
        $untangledFiles = [];
        $fieldPaths = [];
        foreach ($convolutedFiles as $firstLevelFieldName => $fieldInformation) {
            if (!is_array($fieldInformation['error'])) {
                $fieldPaths[] = [$firstLevelFieldName];
            } else {
                $newFieldPaths = $this->calculateFieldPaths($fieldInformation['error'], $firstLevelFieldName);
                array_walk($newFieldPaths, function (&$value, $key) {
                    $value = explode('/', $value);
                });
                $fieldPaths = array_merge($fieldPaths, $newFieldPaths);
            }
        }
        foreach ($fieldPaths as $fieldPath) {
            if (count($fieldPath) === 1) {
                $fileInformation = $convolutedFiles[$fieldPath[0]];
            } else {
                $fileInformation = [];
                foreach ($convolutedFiles[$fieldPath[0]] as $key => $subStructure) {
                    try {
                        $fileInformation[$key] = ArrayUtility::getValueByPath($subStructure, array_slice($fieldPath, 1));
                    } catch (MissingArrayPathException $e) {
                        // do nothing if the path is invalid
                    }
                }
            }
            $untangledFiles = ArrayUtility::setValueByPath($untangledFiles, $fieldPath, $fileInformation);
        }
        return $untangledFiles;
    }

    /**
     * Returns an array of all possibles "field paths" for the given array.
     *
     * @param array $structure The array to walk through
     * @param string $firstLevelFieldName
     * @return array An array of paths (as strings) in the format "key1/key2/key3" ...
     */
    protected function calculateFieldPaths(array $structure, $firstLevelFieldName = null)
    {
        $fieldPaths = [];
        if (is_array($structure)) {
            foreach ($structure as $key => $subStructure) {
                $fieldPath = ($firstLevelFieldName !== null ? $firstLevelFieldName . '/' : '') . $key;
                if (is_array($subStructure)) {
                    foreach ($this->calculateFieldPaths($subStructure) as $subFieldPath) {
                        $fieldPaths[] = $fieldPath . '/' . $subFieldPath;
                    }
                } else {
                    $fieldPaths[] = $fieldPath;
                }
            }
        }
        return $fieldPaths;
    }

    protected function getServerRequestMethod(?ServerRequestInterface $typo3Request): string
    {
        if ($typo3Request instanceof ServerRequestInterface) {
            return $typo3Request->getMethod();
        }
        return isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }
}
