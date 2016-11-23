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
 * Widget routing definitions.
 */
class Widget implements RouteInterface {
    /**
     * {@inheritdoc}
     */
    public static function getPublicNames() : array {
        return [
            'widget:sso',
            'widget:oauth',
            'widget:callback'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function register(App $app) {
        $app->getContainer()[\App\Controller\Widget::class] = function (ContainerInterface $container) {
            return new \App\Controller\Widget(
                $container->get('commandBus'),
                $container->get('commandFactory'),
                $container->get('flash')
            );
        };

        $container = $app->getContainer();

        self::sso($app);
        self::oauth($app);
        self::callback($app);
    }

    /**
     * Redirects user to provider's oAuth screen.
     *
     * @apiEndpoint GET /sso/{provider}
     *
     * @param \Slim\App $app
     *
     * @return void
     */
    private static function sso(App $app) {
        $app
            ->get(
                '/sso/{provider:[a-zA-Z0-9_-]+}/{companySlug:[a-z0-9_-]+}/{credentialPubKey:[a-zA-Z0-9_-]+}',
                'App\Controller\Widget:sso'
            )
            ->setName('widget:sso');
    }

    /**
     * Extract oAuth tokens then send to idOS.
     *
     * @apiEndpoint GET /oauth/{provider}
     *
     * @param \Slim\App $app
     *
     * @return void
     */
    private static function oauth(App $app) {
        $app
            ->get(
                '/oauth/{provider:[a-zA-Z0-9_-]+}/{companySlug:[a-z0-9_-]+}/{credentialPubKey:[a-zA-Z0-9_-]+}',
                'App\Controller\Widget:oauth'
            )
            ->setName('widget:oauth');
    }

    /**
     * Extract oAuth tokens then send to idOS.
     *
     * @apiEndpoint GET /callback/{provider}
     *
     * @param \Slim\App $app
     *
     * @return void
     */
    private static function callback(App $app) {
        $app
            ->get(
                '/callback/{provider:[a-zA-Z0-9_-]+}',
                'App\Controller\Widget:callback'
            )
            ->setName('widget:callback');
    }
}
