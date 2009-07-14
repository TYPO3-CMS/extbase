<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
*  All rights reserved
*
*  This class is a backport of the corresponding class of FLOW3.
*  All credits go to the v5 team.
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * An abstract base class for Controllers
 *
 * @package Extbase
 * @subpackage MVC
 * @version $ID:$
 */
abstract class Tx_Extbase_MVC_Controller_AbstractController implements Tx_Extbase_MVC_Controller_ControllerInterface {

	/**
	 * @var Tx_Extbase_Object_ManageInterface
	 */
	protected $objectManager;

	/**
	 * @var Tx_Extbase_MVC_Web_Routing_URIBuilder
	 */
	protected $URIBuilder;

	/**
	 * @var string Key of the extension this controller belongs to
	 */
	protected $extensionName;

	/**
	 * Contains the settings of the current extension
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * @var Tx_Extbase_MVC_Request The current request
	 */
	protected $request;

	/**
	 * @var Tx_Extbase_MVC_Response The response which will be returned by this action controller
	 */
	protected $response;

	/**
	 * @var Tx_Extbase_Property_Mapper
	 */
	protected $propertyMapper;

	/**
	 * @var Tx_Extbase_Validation_ValidatorResolver
	 */
	protected $validatorResolver;

	/**
	 * @var Tx_Extbase_MVC_Controller_Arguments Arguments passed to the controller
	 */
	protected $arguments;

	/**
	 * The results of the mapping of request arguments to controller arguments
	 * @var Tx_Extbase_Property_MappingResults
	 */
	protected $argumentsMappingResults;

	/**
	 * An array of supported request types. By default only web requests are supported.
	 * Modify or replace this array if your specific controller supports certain
	 * (additional) request types.
	 * @var array
	 */
	protected $supportedRequestTypes = array('Tx_Extbase_MVC_Request');

	/**
	 * Constructs the controller.
	 */
	public function __construct() {
		$this->objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_Manager');
		$this->arguments = t3lib_div::makeInstance('Tx_Extbase_MVC_Controller_Arguments');
		$this->arguments->injectPersistenceManager(t3lib_div::makeInstance('Tx_Extbase_Persistence_Manager')); // singleton; must have been initialized before
		$this->arguments->injectQueryFactory(t3lib_div::makeInstance('Tx_Extbase_Persistence_QueryFactory'));
		list(, $this->extensionName) = explode('_', get_class($this));
	}

	/**
	 * Injects the property mapper
	 *
	 * @param Tx_Extbase_Property_Mapper $propertyMapper The property mapper
	 * @return void
	 * @internal
	 */
	public function injectPropertyMapper(Tx_Extbase_Property_Mapper $propertyMapper) {
		$this->propertyMapper = $propertyMapper;
	}

	/**
	 * Injects the settings of the extension.
	 *
	 * @param array $settings Settings container of the current extension
	 * @return void
	 * @internal
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Injects the validator resolver
	 *
	 * @param Tx_Extbase_Validation_ValidatorResolver $validatorResolver
	 * @return void
	 * @internal
	 */
	public function injectValidatorResolver(Tx_Extbase_Validation_ValidatorResolver $validatorResolver) {
		$this->validatorResolver = $validatorResolver;
	}

	/**
	 * Checks if the current request type is supported by the controller.
	 *
	 * If your controller only supports certain request types, either
	 * replace / modify the supporteRequestTypes property or override this
	 * method.
	 *
	 * @param Tx_Extbase_MVC_Request $request The current request
	 * @return boolean TRUE if this request type is supported, otherwise FALSE
	 */
	public function canProcessRequest(Tx_Extbase_MVC_Request $request) {
		foreach ($this->supportedRequestTypes as $supportedRequestType) {
			if ($request instanceof $supportedRequestType) return TRUE;
		}
		return FALSE;
	}

	/**
	 * Processes a general request. The result can be returned by altering the given response.
	 *
	 * @param Tx_Extbase_MVC_Request $request The request object
	 * @param Tx_Extbase_MVC_Response $response The response, modified by this handler
	 * @return void
	 * @throws Tx_Extbase_Exception_UnsupportedRequestType if the controller doesn't support the current request type
	 */
	public function processRequest(Tx_Extbase_MVC_Request $request, Tx_Extbase_MVC_Response $response) {
		if (!$this->canProcessRequest($request)) throw new Tx_Extbase_MVC_Exception_UnsupportedRequestType(get_class($this) . ' does not support requests of type "' . get_class($request) . '". Supported types are: ' . implode(' ', $this->supportedRequestTypes) , 1187701131);

		$this->request = $request;
		$this->request->setDispatched(TRUE);
		$this->response = $response;

		$this->URIBuilder = t3lib_div::makeInstance('Tx_Extbase_MVC_Web_Routing_URIBuilder');
		$this->URIBuilder->setRequest($request);

		$this->initializeControllerArgumentsBaseValidators();
		$this->mapRequestArgumentsToControllerArguments();
	}

	/**
	 * Initialize the controller context
	 *
	 * @return Tx_Extbase_MVC_Controller_ControllerContext ControllerContext to be passed to the view
	 * @internal
	 */
	protected function buildControllerContext() {
		$controllerContext = t3lib_div::makeInstance('Tx_Extbase_MVC_Controller_ControllerContext');
		$controllerContext->setRequest($this->request);
		$controllerContext->setResponse($this->response);
		if ($this->arguments !== NULL) {
			$controllerContext->setArguments($this->arguments);
		}
		if ($this->argumentsMappingResults !== NULL) {
			$controllerContext->setArgumentsMappingResults($this->argumentsMappingResults);
		}
		$controllerContext->setURIBuilder($this->URIBuilder);
		return $controllerContext;
	}

	/**
	 * Forwards the request to another controller.
	 *
	 * @param string $actionName Name of the action to forward to
	 * @param string $controllerName Unqualified object name of the controller to forward to. If not specified, the current controller is used.
	 * @param string $extensionName Name of the extension containing the controller to forward to. If not specified, the current extension is assumed.
	 * @param Tx_Extbase_MVC_Controller_Arguments $arguments Arguments to pass to the target action
	 * @return void
	 * @throws Tx_Extbase_Exception_StopAction
	 */
	public function forward($actionName, $controllerName = NULL, $extensionName = NULL, array $arguments = NULL) {
		$this->request->setDispatched(FALSE);
		$this->request->setControllerActionName($actionName);
		if ($controllerName !== NULL) $this->request->setControllerName($controllerName);
		if ($extensionName !== NULL) $this->request->setControllerExtensionName($extensionName);
		if ($arguments !== NULL) $this->request->setArguments($arguments);
		throw new Tx_Extbase_Exception_StopAction();
	}

	/**
	 * Forwards the request to another action and / or controller.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception
	 * if used with other request types.
	 *
	 * @param string $actionName Name of the action to forward to
	 * @param string $controllerName Unqualified object name of the controller to forward to. If not specified, the current controller is used.
	 * @param string $extensionName Name of the extension containing the controller to forward to. If not specified, the current extension is assumed.
	 * @param Tx_Extbase_MVC_Controller_Arguments $arguments Arguments to pass to the target action
	 * @param integer $pageUid Target page uid. If NULL, the current page uid is used
	 * @param integer $delay (optional) The delay in seconds. Default is no delay.
	 * @param integer $statusCode (optional) The HTTP status code for the redirect. Default is "303 See Other"
	 * @return void
	 * @throws Tx_Extbase_Exception_UnsupportedRequestType If the request is not a web request
	 * @throws Tx_Extbase_Exception_StopAction
	 */
	protected function redirect($actionName, $controllerName = NULL, $extensionName = NULL, array $arguments = NULL, $pageUid = NULL, $delay = 0, $statusCode = 303) {
		if (!$this->request instanceof Tx_Extbase_MVC_Web_Request) throw new Tx_Extbase_Exception_UnsupportedRequestType('redirect() only supports web requests.', 1220539734);

		if ($controllerName === NULL) {
			$controllerName = $this->request->getControllerName();
		}
		if ($pageUid === NULL) {
			$pageUid = $GLOBALS['TSFE']->id;
		}

		$uri = $this->URIBuilder->URIFor($pageUid, $actionName, $arguments, $controllerName, $extensionName);
		$this->redirectToURI($uri, $delay, $statusCode);
	}

	/**
	 * Redirects the web request to another uri.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception if used with other request types.
	 *
	 * @param mixed $uri A string representation of a URI
	 * @param integer $delay (optional) The delay in seconds. Default is no delay.
	 * @param integer $statusCode (optional) The HTTP status code for the redirect. Default is "303 See Other"
	 * @throws Tx_Extbase_Exception_UnsupportedRequestType If the request is not a web request
	 * @throws Tx_Extbase_Exception_StopAction
	 */
	protected function redirectToURI($uri, $delay = 0, $statusCode = 303) {
		if (!$this->request instanceof Tx_Extbase_MVC_Web_Request) throw new Tx_Extbase_Exception_UnsupportedRequestType('redirect() only supports web requests.', 1220539734);

		$uri = $this->request->getBaseURI() . (string)$uri;
		$escapedUri = htmlentities($uri, ENT_QUOTES, 'utf-8');
		$this->response->setContent('<html><head><meta http-equiv="refresh" content="' . intval($delay) . ';url=' . $escapedUri . '"/></head></html>');
		$this->response->setStatus($statusCode);
		$this->response->setHeader('Location', (string)$uri);
		throw new Tx_Extbase_Exception_StopAction();
	}

	/**
	 * Sends the specified HTTP status immediately.
	 *
	 * NOTE: This method only supports web requests and will thrown an exception if used with other request types.
	 *
	 * @param integer $statusCode The HTTP status code
	 * @param string $statusMessage A custom HTTP status message
	 * @param string $content Body content which further explains the status
	 * @throws Tx_Extbase_Exception_UnsupportedRequestType If the request is not a web request
	 * @throws Tx_Extbase_Exception_StopAction
	 */
	public function throwStatus($statusCode, $statusMessage = NULL, $content = NULL) {
		if (!$this->request instanceof Tx_Extbase_MVC_Web_Request) throw new Tx_Extbase_Exception_UnsupportedRequestType('throwStatus() only supports web requests.', 1220539739);

		$this->response->setStatus($statusCode, $statusMessage);
		if ($content === NULL) $content = $this->response->getStatus();
		$this->response->setContent($content);
		throw new Tx_Extbase_Exception_StopAction();
	}

	/**
	 * Collects the base validators which were defined for the data type of each
	 * controller argument and adds them to the argument's validator chain.
	 *
	 * @return void
	 * @internal
	 */
	public function initializeControllerArgumentsBaseValidators() {
		foreach ($this->arguments as $argument) {
			$validator = $this->validatorResolver->getBaseValidatorConjunction($argument->getDataType());
			if ($validator !== NULL) $argument->setValidator($validator);
		}
	}

	/**
	 * Maps arguments delivered by the request object to the local controller arguments.
	 *
	 * @return void
	 * @internal
	 */
	protected function mapRequestArgumentsToControllerArguments() {
		$optionalPropertyNames = array();
		$allPropertyNames = $this->arguments->getArgumentNames();
		foreach ($allPropertyNames as $propertyName) {
			if ($this->arguments[$propertyName]->isRequired() === FALSE) $optionalPropertyNames[] = $propertyName;
		}

		$validator = t3lib_div::makeInstance('Tx_Extbase_MVC_Controller_ArgumentsValidator');
		$this->propertyMapper->mapAndValidate($allPropertyNames, $this->request->getArguments(), $this->arguments, $optionalPropertyNames, $validator);
		$this->argumentsMappingResults = $this->propertyMapper->getMappingResults();
	}
}
?>
