<?php
namespace TYPO3\CMS\Extbase\Mvc\Web;

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
 * Represents a web request.
 */
class Request extends \TYPO3\CMS\Extbase\Mvc\Request
{
    /**
     * @var \TYPO3\CMS\Extbase\Security\Cryptography\HashService
     */
    protected $hashService;

    /**
     * @var string The requested representation format
     */
    protected $format = 'html';

    /**
     * @var string Contains the request method
     */
    protected $method = 'GET';

    /**
     * @var string
     */
    protected $requestUri;

    /**
     * @var string The base URI for this request - ie. the host and path leading to the index.php
     */
    protected $baseUri;

    /**
     * @var bool TRUE if the current request is cached, false otherwise.
     */
    protected $isCached = false;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @var \TYPO3\CMS\Extbase\Service\EnvironmentService
     */
    protected $environmentService;

    /**
     * @param \TYPO3\CMS\Extbase\Security\Cryptography\HashService $hashService
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function injectHashService(\TYPO3\CMS\Extbase\Security\Cryptography\HashService $hashService)
    {
        $this->hashService = $hashService;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function injectConfigurationManager(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager)
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\EnvironmentService $environmentService
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function injectEnvironmentService(\TYPO3\CMS\Extbase\Service\EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    /**
     * Sets the request method
     *
     * @param string $method Name of the request method
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidRequestMethodException if the request method is not supported
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function setMethod($method)
    {
        if ($method === '' || strtoupper($method) !== $method) {
            throw new \TYPO3\CMS\Extbase\Mvc\Exception\InvalidRequestMethodException('The request method "' . $method . '" is not supported.', 1217778382);
        }
        $this->method = $method;
    }

    /**
     * Returns the name of the request method
     *
     * @return string Name of the request method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the request URI
     *
     * @param string $requestUri URI of this web request
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function setRequestUri($requestUri)
    {
        $this->requestUri = $requestUri;
    }

    /**
     * Returns the request URI
     *
     * @return string URI of this web request
     */
    public function getRequestUri()
    {
        return $this->requestUri;
    }

    /**
     * Sets the base URI for this request.
     *
     * @param string $baseUri New base URI
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function setBaseUri($baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Returns the base URI
     *
     * @return string Base URI of this web request
     */
    public function getBaseUri()
    {
        if ($this->environmentService->isEnvironmentInBackendMode()) {
            return $this->baseUri . TYPO3_mainDir;
        }
        return $this->baseUri;
    }

    /**
     * Set if the current request is cached.
     *
     * @param bool $isCached
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function setIsCached($isCached)
    {
        $this->isCached = (bool)$isCached;
    }

    /**
     * Return whether the current request is a cached request or not.
     *
     * @return bool the caching status.
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function isCached()
    {
        return $this->isCached;
    }

    /**
     * Get a freshly built request object pointing to the Referrer.
     *
     * @return ReferringRequest the referring request, or null if no referrer found
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function getReferringRequest()
    {
        if (isset($this->internalArguments['__referrer']['@request'])) {
            $referrerArray = unserialize($this->hashService->validateAndStripHmac($this->internalArguments['__referrer']['@request']), ['allowed_classes' => false]);
            $arguments = [];
            if (isset($this->internalArguments['__referrer']['arguments'])) {
                // This case is kept for compatibility in 7.6 and 6.2, but will be removed in 8
                $arguments = unserialize(base64_decode($this->hashService->validateAndStripHmac($this->internalArguments['__referrer']['arguments'])));
            }
            $referringRequest = new ReferringRequest();
            $referringRequest->setArguments(array_replace_recursive($arguments, $referrerArray));
            return $referringRequest;
        }
        return null;
    }
}
