<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Route;

use Interop\Container\ContainerInterface;
use Slim\App;

/**
 * Olc routing definitions.
 */
class Olc implements RouteInterface {
    /**
     * {@inheritdoc}
     */
    public static function getPublicNames() : array {
        return [
            'olc:getOne',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function register(App $app) {
        $app->getContainer()[\App\Controller\Olc::class] = function (ContainerInterface $container) {
            return new \App\Controller\Olc(
                $container->get('commandBus'),
                $container->get('commandFactory'),
                $container->get('idosSDK')
            );
        };

        $container            = $app->getContainer();

        self::getOne($app);
    }

    /**
     * Redirects user to provider's oAuth screen.
     *
     * @apiEndpoint GET /getOne/{provider}
     * 
     * @param \Slim\App $app
     *
     * @return void
     */
    private static function getOne(App $app) {
        $app
            ->get(
                '/olc/{widgetHash:[a-z0-9_-]+}',
                'App\Controller\Olc:getOne'
            )
            ->setName('widgets:olc');
    }

}
