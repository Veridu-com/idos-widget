<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CORS Middleware.
 *
 * Cross Origin Resource Sharing header control.
 *
 * @link https://en.wikipedia.org/wiki/Cross-origin_resource_sharing
 */
class CORS implements MiddlewareInterface{
    private $methods;

    public function __construct(array $methods = []) {
        if (! in_array('OPTIONS', $methods)) {
            $methods[] = 'OPTIONS';
        }

        $this->methods = $methods;
    }

    /**
     * Middleware execution, adds Cross-origin Resource Sharing (CORS)
     * Headers to responses.
     *
     * @apiEndpointRespHeader Access-Control-Allow-Origin *
     * @apiEndpointRespHeader Access-Control-Max-Age 3628800
     * @apiEndpointRespHeader Access-Control-Allow-Credentials true
     * @apiEndpointRespHeader Access-Control-Allow-Methods ...
     * @apiEndpointRespHeader Access-Control-Allow-Headers Authorization, Content-Type, If-Modified-Since, If-None-Match, X-Requested-With
     * @apiEndpointRespHeader Access-Control-Expose-Headers ETag, X-Rate-Limit-Limit, X-Rate-Limit-Remaining, X-Rate-Limit-Reset
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     * @param callable                                 $next
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next) : ResponseInterface {
        if (! empty($request->getHeaderLine('Origin')))
            $response = $response
                ->withHeader(
                    'Access-Control-Allow-Origin',
                    $request->getHeaderLine('Origin')
                )
                ->withHeader(
                    'Access-Control-Max-Age',
                    '3628800'
                )
                ->withHeader(
                    'Access-Control-Allow-Credentials',
                    'true'
                )
                ->withHeader(
                    'Access-Control-Allow-Methods',
                    implode(',', $this->methods)
                )
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'Authorization, Content-Type, If-Modified-Since, If-None-Match, X-Requested-With'
                )
                ->withHeader(
                    'Access-Control-Expose-Headers',
                    'ETag, X-Rate-Limit-Limit, X-Rate-Limit-Remaining, X-Rate-Limit-Reset'
                );

        return $next($request, $response);
    }
}
