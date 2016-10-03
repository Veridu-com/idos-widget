<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Route;

use App\Middleware\Auth;
use Interop\Container\ContainerInterface;
use Slim\App;

/**
 * Root routing definitions.
 *
 * @link docs/overview.md
 * @see App\Controller\Main
 */
class Main implements RouteInterface {
    /**
     * {@inheritdoc}
     */
    public static function getPublicNames() : array {
        return [
            'main:listAll'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function register(App $app) {
        $app->getContainer()[\App\Controller\Main::class] = function (ContainerInterface $container) {
            return new \App\Controller\Main(
                $container->get('router'),
                $container->get('commandBus'),
                $container->get('commandFactory')
            );
        };

        $container      = $app->getContainer();
        $authMiddleware = $container->get('authMiddleware');

        self::listAll($app, $authMiddleware);
    }

    /**
     * List all Endpoints.
     *
     * Retrieve a complete list of all public endpoints.
     *
     * @apiEndpoint GET /
     * @apiGroup General
     * @apiEndpointResponse 200 schema/listAll.json
     *
     * @param \Slim\App $app
     *
     * @return void
     *
     * @link docs/listAll.md
     * @see App\Controller\Main::listAll
     */
    private static function listAll(App $app, callable $authMiddleware) {
        $app
            ->get(
                '/',
                'App\Controller\Main:listAll'
            )
            ->add($authMiddleware(Auth::NONE))
            ->setName('main:listAll');
    }
}
