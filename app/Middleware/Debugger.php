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
 * Debugger Middleware.
 *
 * Allows requests to force a specific error response for development.
 */
class Debugger implements MiddlewareInterface {
    private function protectedException($class) {
        $class = str_replace('\\App\\Exception\\', '', $class);

        return in_array(
            $class,
            ['AppException']
        );
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next) : ResponseInterface {
        $queryParams = $request->getQueryParams();
        if (empty($queryParams['forcedError']))
            return $next($request, $response);

        $class = explode('_', $queryParams['forcedError']);
        foreach ($class as &$item)
            $item = ucfirst(strtolower($item));
        $class    = sprintf('\\App\\Exception\\%s', implode('', $class));
        if ((! $this->protectedException($class)) && (class_exists($class)))
            throw new $class();
        throw new \Exception('UnknownError');
    }
}
