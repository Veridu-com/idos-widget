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
 * SSO routing definitions.
 *
 * @link docs/login/overview.md
 * @see App\Controller\SSO
 */
class SSO implements RouteInterface {
    /**
     * {@inheritdoc}
     */
    public static function getPublicNames() : array {
        return [
            'sso:login',
            'sso:callback'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function register(App $app) {
        $app->getContainer()[\App\Controller\SSO::class] = function (ContainerInterface $container) {
            return new \App\Controller\SSO(
                $container->get('commandBus'),
                $container->get('commandFactory'),
                $container->get('flash')
            );
        };

        $container            = $app->getContainer();

        self::login($app);
        self::callback($app);
    }

    /**
     * Extract oAuth tokens then send to idOS.
     *
     * @apiEndpoint GET /login/{provider}
     * 
     * @param \Slim\App $app
     *
     * @return void
     */
    private static function login(App $app) {
        $app
            ->get(
                '/login/{provider:[a-zA-Z0-9_-]+}/{credentialPubKey:[a-zA-Z0-9_-]+}',
                'App\Controller\SSO:login'
            )
            ->setName('sso:login');
    }

    /**
     * Extract oAuth tokens then send to idOS.
     *
     * @apiEndpoint GET /login/{provider}
     * 
     * @param \Slim\App $app
     *
     * @return void
     */
    private static function callback(App $app) {
        $app
            ->get(
                '/callback/{provider:[a-zA-Z0-9_-]+}',
                'App\Controller\SSO:callback'
            )
            ->setName('sso:callback');
    }

}
