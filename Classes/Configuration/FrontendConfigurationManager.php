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

namespace TYPO3\CMS\Extbase\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Event\Configuration\BeforeFlexFormConfigurationOverrideEvent;

/**
 * A general purpose configuration manager used in frontend mode.
 *
 * Should NOT be singleton, as a new configuration manager is needed per plugin.
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class FrontendConfigurationManager implements SingletonInterface
{
    /**
     * Storage of the raw TypoScript configuration
     *
     * @var array
     */
    protected $configuration = [];

    /**
     * name of the extension this Configuration Manager instance belongs to
     *
     * @var string
     */
    protected $extensionName;

    /**
     * name of the plugin this Configuration Manager instance belongs to
     *
     * @var string
     */
    protected $pluginName;

    /**
     * 1st level configuration cache
     *
     * @var array
     */
    protected $configurationCache = [];

    /**
     * @todo: In v13, this shouldn't be nullable, FE context *always* has a request.
     */
    private ?ServerRequestInterface $request = null;

    public function __construct(
        protected TypoScriptService $typoScriptService,
        protected FlexFormService $flexFormService,
        protected PageRepository $pageRepository,
        protected EventDispatcher $eventDispatcher
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Sets the specified raw configuration coming from the outside.
     * Note that this is a low level method and only makes sense to be used by Extbase internally.
     *
     * @param array $configuration The new configuration
     */
    public function setConfiguration(array $configuration = []): void
    {
        // reset 1st level cache
        $this->configurationCache = [];
        $this->extensionName = $configuration['extensionName'] ?? null;
        $this->pluginName = $configuration['pluginName'] ?? null;
        $this->configuration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($configuration);
    }

    /**
     * Loads the Extbase Framework configuration.
     *
     * The Extbase framework configuration HAS TO be retrieved using this method, as they are come from different places than the normal settings.
     * Framework configuration is, in contrast to normal settings, needed for the Extbase framework to operate correctly.
     *
     * @param string|null $extensionName if specified, the configuration for the given extension will be returned (plugin.tx_extensionname)
     * @param string|null $pluginName if specified, the configuration for the given plugin will be returned (plugin.tx_extensionname_pluginname)
     * @return array the Extbase framework configuration
     */
    public function getConfiguration(?string $extensionName = null, ?string $pluginName = null): array
    {
        // 1st level cache
        $configurationCacheKey = strtolower(($extensionName ?: $this->extensionName) . '_' . ($pluginName ?: $this->pluginName));
        if (isset($this->configurationCache[$configurationCacheKey])) {
            return $this->configurationCache[$configurationCacheKey];
        }
        $frameworkConfiguration = $this->getExtbaseConfiguration();
        if (!isset($frameworkConfiguration['persistence']['storagePid'])) {
            $frameworkConfiguration['persistence']['storagePid'] = 0;
        }
        // only merge $this->configuration and override controller configuration when retrieving configuration of the current plugin
        if ($extensionName === null || $extensionName === $this->extensionName && $pluginName === $this->pluginName) {
            $pluginConfiguration = $this->getPluginConfiguration((string)$this->extensionName, (string)$this->pluginName);
            ArrayUtility::mergeRecursiveWithOverrule($pluginConfiguration, $this->configuration);
            $pluginConfiguration['controllerConfiguration'] = $this->getControllerConfiguration((string)$this->extensionName, (string)$this->pluginName);
        } else {
            $pluginConfiguration = $this->getPluginConfiguration((string)$extensionName, (string)$pluginName);
            $pluginConfiguration['controllerConfiguration'] = $this->getControllerConfiguration((string)$extensionName, (string)$pluginName);
        }
        ArrayUtility::mergeRecursiveWithOverrule($frameworkConfiguration, $pluginConfiguration);
        // only load context specific configuration when retrieving configuration of the current plugin
        if ($extensionName === null || $extensionName === $this->extensionName && $pluginName === $this->pluginName) {
            $frameworkConfiguration = $this->getContextSpecificFrameworkConfiguration($frameworkConfiguration);
        }

        if (!empty($frameworkConfiguration['persistence']['storagePid'])) {
            if (is_array($frameworkConfiguration['persistence']['storagePid'])) {
                $conf = $this->typoScriptService->convertPlainArrayToTypoScriptArray($frameworkConfiguration['persistence']);
                $frameworkConfiguration['persistence']['storagePid'] = $GLOBALS['TSFE']->cObj->stdWrapValue('storagePid', $conf);
            }
            if (!empty($frameworkConfiguration['persistence']['recursive'])) {
                $storagePids = $this->getRecursiveStoragePids(
                    GeneralUtility::intExplode(',', (string)($frameworkConfiguration['persistence']['storagePid'] ?? '')),
                    (int)$frameworkConfiguration['persistence']['recursive']
                );
                $frameworkConfiguration['persistence']['storagePid'] = implode(',', $storagePids);
            }
        }
        // 1st level cache
        $this->configurationCache[$configurationCacheKey] = $frameworkConfiguration;
        return $frameworkConfiguration;
    }

    /**
     * Returns the TypoScript configuration found in config.tx_extbase
     */
    protected function getExtbaseConfiguration(): array
    {
        $setup = $this->getTypoScriptSetup();
        $extbaseConfiguration = [];
        if (isset($setup['config.']['tx_extbase.'])) {
            $extbaseConfiguration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($setup['config.']['tx_extbase.']);
        }
        return $extbaseConfiguration;
    }

    /**
     * Returns full Frontend TypoScript setup array calculated by FE middlewares.
     */
    public function getTypoScriptSetup(): array
    {
        // @todo: Avoid $GLOBALS['TYPO3_REQUEST'] in v13.
        /** @var ServerRequestInterface $request */
        $request = $this->request ?? $GLOBALS['TYPO3_REQUEST'];
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        return $frontendTypoScript->getSetupArray();
    }

    /**
     * Returns the TypoScript configuration found in plugin.tx_yourextension_yourplugin
     * merged with the global configuration of your extension from plugin.tx_yourextension
     *
     * @param string|null $pluginName in FE mode this is the specified plugin name
     */
    protected function getPluginConfiguration(string $extensionName, string $pluginName = null): array
    {
        $setup = $this->getTypoScriptSetup();
        $pluginConfiguration = [];
        if (isset($setup['plugin.']['tx_' . strtolower($extensionName) . '.']) && is_array($setup['plugin.']['tx_' . strtolower($extensionName) . '.'])) {
            $pluginConfiguration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($setup['plugin.']['tx_' . strtolower($extensionName) . '.']);
        }
        if ($pluginName !== null) {
            $pluginSignature = strtolower($extensionName . '_' . $pluginName);
            if (isset($setup['plugin.']['tx_' . $pluginSignature . '.']) && is_array($setup['plugin.']['tx_' . $pluginSignature . '.'])) {
                ArrayUtility::mergeRecursiveWithOverrule(
                    $pluginConfiguration,
                    $this->typoScriptService->convertTypoScriptArrayToPlainArray($setup['plugin.']['tx_' . $pluginSignature . '.'])
                );
            }
        }
        return $pluginConfiguration;
    }

    /**
     * Returns the configured controller/action configuration of the specified plugin in the format
     * array(
     * 'Controller1' => array('action1', 'action2'),
     * 'Controller2' => array('action3', 'action4')
     * )
     *
     * @param string $pluginName in FE mode this is the specified plugin name
     */
    protected function getControllerConfiguration(string $extensionName, string $pluginName): array
    {
        $controllerConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'][$extensionName]['plugins'][$pluginName]['controllers'] ?? [];
        if (!is_array($controllerConfiguration)) {
            $controllerConfiguration = [];
        }
        return $controllerConfiguration;
    }

    /**
     * Get context specific framework configuration.
     * - Overrides storage PID with setting "Startingpoint"
     * - merge flexForm configuration, if needed
     *
     * @param array $frameworkConfiguration The framework configuration to modify
     * @return array the modified framework configuration
     */
    protected function getContextSpecificFrameworkConfiguration(array $frameworkConfiguration): array
    {
        $frameworkConfiguration = $this->overrideStoragePidIfStartingPointIsSet($frameworkConfiguration);
        $frameworkConfiguration = $this->overrideConfigurationFromPlugin($frameworkConfiguration);
        $frameworkConfiguration = $this->overrideConfigurationFromFlexForm($frameworkConfiguration);
        return $frameworkConfiguration;
    }

    /**
     * Overrides the storage PID settings, in case the "Startingpoint" settings
     * is set in the plugin configuration.
     *
     * @param array $frameworkConfiguration the framework configurations
     * @return array the framework configuration with overridden storagePid
     */
    protected function overrideStoragePidIfStartingPointIsSet(array $frameworkConfiguration): array
    {
        $contentObject = $this->request?->getAttribute('currentContentObject');
        $pages = (string)($contentObject?->data['pages'] ?? '');
        if ($pages !== '') {
            $storagePids = GeneralUtility::intExplode(',', $pages, true);
            $recursionDepth = (int)($contentObject?->data['recursive'] ?? 0);
            $recursiveStoragePids = $this->pageRepository->getPageIdsRecursive($storagePids, $recursionDepth);
            $pages = implode(',', $recursiveStoragePids);
            ArrayUtility::mergeRecursiveWithOverrule($frameworkConfiguration, [
                'persistence' => [
                    'storagePid' => $pages,
                ],
            ]);
        }
        return $frameworkConfiguration;
    }

    /**
     * Overrides configuration settings from the plugin typoscript (plugin.tx_myext_pi1.)
     *
     * @param array $frameworkConfiguration the framework configuration
     * @return array the framework configuration with overridden data from typoscript
     */
    protected function overrideConfigurationFromPlugin(array $frameworkConfiguration): array
    {
        if (!isset($frameworkConfiguration['extensionName']) || !isset($frameworkConfiguration['pluginName'])) {
            return $frameworkConfiguration;
        }

        $setup = $this->getTypoScriptSetup();
        $pluginSignature = strtolower($frameworkConfiguration['extensionName'] . '_' . $frameworkConfiguration['pluginName']);
        $pluginConfiguration = $setup['plugin.']['tx_' . $pluginSignature . '.'] ?? null;
        if (is_array($pluginConfiguration)) {
            $pluginConfiguration = $this->typoScriptService->convertTypoScriptArrayToPlainArray($pluginConfiguration);
            $frameworkConfiguration = $this->mergeConfigurationIntoFrameworkConfiguration($frameworkConfiguration, $pluginConfiguration, 'settings');
            $frameworkConfiguration = $this->mergeConfigurationIntoFrameworkConfiguration($frameworkConfiguration, $pluginConfiguration, 'persistence');
            $frameworkConfiguration = $this->mergeConfigurationIntoFrameworkConfiguration($frameworkConfiguration, $pluginConfiguration, 'view');
        }
        return $frameworkConfiguration;
    }

    /**
     * Overrides configuration settings from flexForms. This merges the whole flexForm data.
     *
     * @param array $frameworkConfiguration the framework configuration
     * @return array the framework configuration with overridden data from flexForm
     */
    protected function overrideConfigurationFromFlexForm(array $frameworkConfiguration): array
    {
        $contentObject = $this->request?->getAttribute('currentContentObject');
        $flexFormConfiguration = $contentObject?->data['pi_flexform'] ?? [];
        if (is_string($flexFormConfiguration)) {
            if ($flexFormConfiguration !== '') {
                $flexFormConfiguration = $this->flexFormService->convertFlexFormContentToArray($flexFormConfiguration);
            } else {
                $flexFormConfiguration = [];
            }
        }

        // Early return, if flexForm configuration is empty
        if (!is_array($flexFormConfiguration) || empty($flexFormConfiguration)) {
            return $frameworkConfiguration;
        }

        // Remove flexForm settings if empty for fields defined in `ignoreFlexFormSettingsIfEmpty`
        $originalFlexFormConfiguration = $flexFormConfiguration;
        $ignoredSettingsConfig = (string)($frameworkConfiguration['ignoreFlexFormSettingsIfEmpty'] ?? '');
        if ($ignoredSettingsConfig !== '') {
            $ignoredSettings = GeneralUtility::trimExplode(',', $ignoredSettingsConfig, true);
            $flexFormConfiguration = $this->removeIgnoredFlexFormSettingsIfEmpty($flexFormConfiguration, $ignoredSettings);
        }

        // PSR-14 event for extension authors to modify flexForm configuration before the merge process
        $event = new BeforeFlexFormConfigurationOverrideEvent($frameworkConfiguration, $originalFlexFormConfiguration, $flexFormConfiguration);
        $this->eventDispatcher->dispatch($event);
        $flexFormConfiguration = $event->getFlexFormConfiguration();

        $frameworkConfiguration = $this->mergeConfigurationIntoFrameworkConfiguration($frameworkConfiguration, $flexFormConfiguration, 'settings');
        $frameworkConfiguration = $this->mergeConfigurationIntoFrameworkConfiguration($frameworkConfiguration, $flexFormConfiguration, 'persistence');
        $frameworkConfiguration = $this->mergeConfigurationIntoFrameworkConfiguration($frameworkConfiguration, $flexFormConfiguration, 'view');

        return $frameworkConfiguration;
    }

    /**
     * Merge a configuration into the framework configuration.
     *
     * @param array $frameworkConfiguration the framework configuration to merge the data on
     * @param array $configuration The configuration
     * @param string $configurationPartName The name of the configuration part which should be merged.
     * @return array the processed framework configuration
     */
    protected function mergeConfigurationIntoFrameworkConfiguration(array $frameworkConfiguration, array $configuration, string $configurationPartName): array
    {
        if (isset($configuration[$configurationPartName]) && is_array($configuration[$configurationPartName])) {
            if (isset($frameworkConfiguration[$configurationPartName]) && is_array($frameworkConfiguration[$configurationPartName])) {
                ArrayUtility::mergeRecursiveWithOverrule($frameworkConfiguration[$configurationPartName], $configuration[$configurationPartName]);
            } else {
                $frameworkConfiguration[$configurationPartName] = $configuration[$configurationPartName];
            }
        }
        return $frameworkConfiguration;
    }

    /**
     * Returns a comma separated list of storagePid that are below a certain storage pid.
     *
     * @param array|int[] $storagePids Storage PIDs to start at; multiple PIDs possible as comma-separated list
     * @param int $recursionDepth Maximum number of levels to search, 0 to disable recursive lookup
     * @return int[] storage PIDs
     */
    protected function getRecursiveStoragePids(array $storagePids, int $recursionDepth = 0): array
    {
        return $this->pageRepository->getPageIdsRecursive($storagePids, $recursionDepth);
    }

    protected function removeIgnoredFlexFormSettingsIfEmpty(array $flexFormConfiguration, array $ignoredSettings): array
    {
        foreach ($ignoredSettings as $ignoredSetting) {
            $ignoredSettingName = 'settings.' . $ignoredSetting;
            if (!ArrayUtility::isValidPath($flexFormConfiguration, $ignoredSettingName, '.')) {
                continue;
            }

            $fieldValue = ArrayUtility::getValueByPath($flexFormConfiguration, $ignoredSettingName, '.');
            if ($fieldValue === '' || $fieldValue === '0') {
                $flexFormConfiguration = ArrayUtility::removeByPath($flexFormConfiguration, $ignoredSettingName, '.');
            }
        }

        return $flexFormConfiguration;
    }
}
