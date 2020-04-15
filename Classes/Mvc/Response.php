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

namespace TYPO3\CMS\Extbase\Mvc;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * A generic and very basic response implementation
 */
class Response implements ResponseInterface
{
    /**
     * @var string The response content
     */
    protected $content;

    /**
     * The HTTP headers which will be sent in the response
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Additional header tags
     *
     * @var array
     */
    protected $additionalHeaderData = [];

    /**
     * The HTTP status code
     *
     * @var int
     */
    protected $statusCode;

    /**
     * The HTTP status message
     *
     * @var string
     */
    protected $statusMessage = 'OK';

    /**
     * The Request which generated the Response
     *
     * @var \TYPO3\CMS\Extbase\Mvc\Request
     */
    protected $request;

    /**
     * The standardized and other important HTTP Status messages
     *
     * @var array
     */
    protected $statusMessages = [
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy', // Deprecated
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        // SERVER ERROR
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        511 => 'Network Authentication Required',
    ];

    /**
     * Overrides and sets the content of the response
     *
     * @param string $content The response content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * Appends content to the already existing content.
     *
     * @param string $content More response content
     */
    public function appendContent($content)
    {
        $this->content .= $content;
    }

    /**
     * Returns the response content without sending it.
     *
     * @return string The response content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Fetches the content, returns and clears it.
     *
     * @return string
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function shutdown()
    {
        $content = $this->getContent();
        $this->setContent('');
        return $content;
    }

    /**
     * Returns the content of the response.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * Sets the HTTP status code and (optionally) a customized message.
     *
     * @param int $code The status code
     * @param string $message If specified, this message is sent instead of the standard message
     * @throws \InvalidArgumentException if the specified status code is not valid
     */
    public function setStatus($code, $message = null)
    {
        if (!is_int($code)) {
            throw new \InvalidArgumentException('The HTTP status code must be of type integer, ' . gettype($code) . ' given.', 1220526013);
        }
        if ($message === null && !isset($this->statusMessages[$code])) {
            throw new \InvalidArgumentException('No message found for HTTP status code "' . $code . '".', 1220526014);
        }
        $this->statusCode = $code;
        $this->statusMessage = $message ?? $this->statusMessages[$code];
    }

    /**
     * Returns status code and status message.
     *
     * @return string The status code and status message, eg. "404 Not Found
     */
    public function getStatus()
    {
        return $this->statusCode . ' ' . $this->statusMessage;
    }

    /**
     * Returns the status code, if not set, uses the OK status code 200
     *
     * @return int
     * @internal only use for backend module handling
     */
    public function getStatusCode()
    {
        return $this->statusCode ?: 200;
    }

    /**
     * Sets the specified HTTP header
     *
     * @param string $name Name of the header, for example "Location", "Content-Description" etc.
     * @param mixed $value The value of the given header
     * @param bool $replaceExistingHeader If a header with the same name should be replaced. Default is TRUE.
     * @throws \InvalidArgumentException
     */
    public function setHeader($name, $value, $replaceExistingHeader = true)
    {
        if (stripos($name, 'HTTP') === 0) {
            throw new \InvalidArgumentException('The HTTP status header must be set via setStatus().', 1220541963);
        }
        if ($replaceExistingHeader === true || !isset($this->headers[$name])) {
            $this->headers[$name] = [$value];
        } else {
            $this->headers[$name][] = $value;
        }
    }

    /**
     * Returns the HTTP headers - including the status header - of this web response
     *
     * @return string[] The HTTP headers
     */
    public function getHeaders()
    {
        $preparedHeaders = [];
        if ($this->statusCode !== null) {
            $protocolVersion = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
            $statusHeader = $protocolVersion . ' ' . $this->statusCode . ' ' . $this->statusMessage;
            $preparedHeaders[] = $statusHeader;
        }
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                $preparedHeaders[] = $name . ': ' . $value;
            }
        }
        return $preparedHeaders;
    }

    /**
     * Returns the HTTP headers grouped by name without the status header
     *
     * @return array all headers set for this request
     * @internal only used within TYPO3 Core to convert to PSR-7 response headers
     */
    public function getUnpreparedHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sends the HTTP headers.
     *
     * If headers have already been sent, this method fails silently.
     */
    public function sendHeaders()
    {
        if (headers_sent() === true) {
            return;
        }
        foreach ($this->getHeaders() as $header) {
            header($header);
        }
    }

    /**
     * Renders and sends the whole web response
     */
    public function send()
    {
        $this->sendHeaders();
        if ($this->content !== null) {
            echo $this->getContent();
        }
    }

    /**
     * Adds an additional header data (something like
     * '<script src="myext/Resources/JavaScript/my.js"></script>'
     * )
     *
     * @TODO The workaround and the $request member should be removed again, once the PageRender does support non-cached USER_INTs
     * @param string $additionalHeaderData The value additional header
     * @throws \InvalidArgumentException
     */
    public function addAdditionalHeaderData($additionalHeaderData)
    {
        if (!is_string($additionalHeaderData)) {
            throw new \InvalidArgumentException('The additional header data must be of type String, ' . gettype($additionalHeaderData) . ' given.', 1237370877);
        }
        if ($this->request->isCached()) {
            /** @var PageRenderer $pageRenderer */
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addHeaderData($additionalHeaderData);
        } else {
            $this->additionalHeaderData[] = $additionalHeaderData;
        }
    }

    /**
     * Returns the additional header data
     *
     * @return array The additional header data
     */
    public function getAdditionalHeaderData()
    {
        return $this->additionalHeaderData;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Mvc\Request $request
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Mvc\Request
     * @internal only to be used within Extbase, not part of TYPO3 Core API.
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}
