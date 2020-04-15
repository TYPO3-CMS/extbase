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

use TYPO3\CMS\Core\Core\Environment;

/**
 * A request handler which can handle web requests invoked by the backend.
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class BackendRequestHandler extends AbstractRequestHandler
{
    /**
     * Handles the web request. The response will automatically be sent to the client.
     *
     * @return \TYPO3\CMS\Extbase\Mvc\Web\Response
     */
    public function handleRequest()
    {
        $request = $this->requestBuilder->build();
        /** @var \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response */
        $response = $this->objectManager->get(Response::class);
        $this->dispatcher->dispatch($request, $response);
        return $response;
    }

    /**
     * This request handler can handle a web request invoked by the backend.
     *
     * @return bool If we are in backend mode TRUE otherwise FALSE
     */
    public function canHandleRequest()
    {
        return $this->environmentService->isEnvironmentInBackendMode() && !Environment::isCli();
    }
}
