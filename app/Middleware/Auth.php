<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authorization Middleware.
 *
 * Extracts authorization from request and stores Acting/Target
 * Subjects (User and/or Company) to request.
 */
class Auth implements MiddlewareInterface {
    /**
     * Http realm.
     *
     * @var string
     */
    private $realm = 'idos';
    /**
     * Authorization Requirement Bitmask.
     *
     * @var int
     */
    private $authorizationRequirement;

    /**
     * Public access
     * Scope: Public.
     *
     * @const NONE No authorization
     */
    const NONE = 0x00;

    /**
     * Returns an authorization setup array based on available
     * authorization constants and internal handler functions.
     *
     * @return array
     */
    private function authorizationSetup() : array {
        return [
        ];
    }

    /**
     * Extracts Authorization from Request Header or Request Query Parameter.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param string                                   $name
     *
     * @return string|null
     */
    private function extractAuthorization(ServerRequestInterface $request, $name) {
        $name  = ucfirst($name);
        $regex = sprintf('/^%s ([a-zA-Z0-9]+)$/', $name);
        if (preg_match($regex, $request->getHeaderLine('Authorization'), $matches))
            return $matches[1];

        $name        = lcfirst($name);
        $queryParams = $request->getQueryParams();

        if (isset($queryParams[$name]))
            return $queryParams[$name];

        return;
    }

    /**
     * Handles request Basic Authorization.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    private function handleBasicAuthorization(ServerRequestInterface $request) {
        // should come from configuration
        $user     = 'idos';
        $password = 'secret';

        $this->validateServerRequest();

        $input = [
            'user'      => $_SERVER['PHP_AUTH_USER'],
            'password'  => $_SERVER['PHP_AUTH_PW']
        ];

        $valid = ($password == $input['password']) && ($user == $input['user']);

        if (! $valid) {
            $this->reject();
        }

        return $request;
    }

    /**
     * Rejects sending http.
     */
    private function reject() {
        header(sprintf('WWW-Authenticate: Digest realm="%s",qop="auth",nonce="%s",opaque="%s"', $this->realm, uniqid(), md5($this->realm)));
        header('HTTP/1.0 401 Unauthorized');
        die('Not authorized');
    }

    private function validateServerRequest() {
        if (! isset($_SERVER['PHP_AUTH_USER']) || ! isset($_SERVER['PHP_AUTH_PW'])) {
            $this->reject();
        }
    }

    /**
     * Class constructor.
     *
     * @param int $authorizationRequirement
     *
     * @return void
     */
    public function __construct($authorizationRequirement = self::NONE) {
        $this->authorizationRequirement = $authorizationRequirement;
    }

    /**
     * Middleware execution, tries to extract authorization key from request and creates
     * request arguments for Acting User, Target User, Acting Company, Target Company and Credential.
     *
     * Acting User: the user that is performing the system action
     * Target User: the user that is receiving the system action
     * Acting Company: the company that is performing the system action
     * Target Company: the company that is receiving the system action
     * Credential: the credential used during the request (may be missing)
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     * @param callable                                 $next
     *
     * @throws App\Exception\AppException
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next) : ResponseInterface {

        $hasAuthorization   = ($this->authorizationRequirement == self::NONE);
        $validAuthorization = [];

        // Authorization Handling Loop
        foreach ($this->authorizationSetup() as $level => $authorizationInfo) {
            if ($hasAuthorization)
                break;

            if (($this->authorizationRequirement & $level) == $level) {
                // Tries to extract Authorization from Request
                $authorization = $this->extractAuthorization($request, $authorizationInfo['name']);
                $request       = $this->{$authorizationInfo['handler']}($request, $authorization);
            }
        }

        return $next($request, $response);
    }
}
