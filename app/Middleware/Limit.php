<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Middleware;

use App\Exception\AppException;
use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stash\Item;

/**
 * Limit Middleware.
 *
 * Enforces request limits and adds usage details to response headers.
 */
class Limit implements MiddlewareInterface {
    private $container;
    private $softLimit;
    private $hardLimit;
    private $limitType;

    const KEYLIMIT = 0x00;
    const USRLIMIT = 0x01;

    public function __construct(ContainerInterface $container, $softLimit, $hardLimit, $limitType = self::KEYLIMIT) {
        $this->container = $container;
        $this->softLimit = $softLimit;
        $this->hardLimit = $hardLimit;
        $this->limitType = $limitType;
    }

    /**
     * Middleware execution, forces request limitting based on usage.
     *
     * @apiEndpointRespHeader X-Rate-Limit-Limit 1
     * @apiEndpointRespHeader X-Rate-Limit-Remaining 1
     * @apiEndpointRespHeader X-Rate-Limit-Reset 1
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
        $key = $request->getAttribute('key');
        if ($this->limitType == self::KEYLIMIT)
            // The limit is based on the key / route identifier
            $controlKey = sprintf(
                '/limit/key/%d/%s',
                $key->getId(),
                $request->getAttribute('route')->getIdentifier()
            );
        elseif ($this->limitType == self::USRLIMIT)
            // The limit is based on the key / user / route identifier
            $controlKey = sprintf(
                '/limit/user/%d/%s/%s',
                $key->getId(),
                $request->getAttribute('user')->getUserName(),
                $request->getAttribute('route')->getIdentifier()
            );

        $item = $this->container->get('cache')->getItem($controlKey);

        $limitControl = $item->get(Item::SP_VALUE, null);

        if (empty($limitControl))
            $limitControl = [
                'usage' => 0,
                'reset' => (time() + 3600)
            ];

        // Increase usage counter
        $limitControl['usage']++;

        $response = $response
            ->withHeader('X-Rate-Limit-Limit', $this->hardLimit)
            ->withHeader('X-Rate-Limit-Remaining', ($this->hardLimit - $limitControl['usage']))
            ->withHeader('X-Rate-Limit-Reset', $limitControl['reset']);

        if ($item->isMiss()) {
            // First request to be monitored
            $item->lock();
            $item->set($limitControl, 3600);

            return $next($request, $response);
        }

        // Above hard limit requests are logged and throw an Exception
        if ($limitControl['usage'] >= $this->hardLimit) {
            $log = $this->container->get('log');
            $log('LimitMiddleware')->notice(
                sprintf(
                    'Limit: over hard threhold: %s (%s %s) [%d]',
                    $key->getPublicKey(),
                    $request->getMethod(),
                    $request->getURI()->getPath(),
                    $this->limitType
                )
            );
            throw new AppException('429 Too Many Requests');
        }

        // Above soft limit requests are logged only
        if ($limitControl['usage'] >= $this->softLimit) {
            $log = $this->container->get('log');
            $log('LimitMiddleware')->notice(
                sprintf(
                    'Limit: over soft threshold: %s (%s %s) [%d]',
                    $key->getPublicKey(),
                    $request->getMethod(),
                    $request->getURI()->getPath(),
                    $this->limitType
                )
            );
        }

        $item->set($limitControl, ($limitControl['reset'] - time()));

        return $next($request, $response);
    }
}
