<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

if (! isset($app)) {
    die('$app is not set!');
}

/**
 * This file is responsible for initializing the event emitter
 * variable that will be inject through the application.
 **/
$emitter = $container->get('eventEmitter');

$providers = array_map(
    function ($providerFile) {
        return preg_replace(
            '/.*?Listener\/(.*)\/(.*)Provider.php/',
            'App\\Listener\\\$1\\\$2Provider',
            $providerFile
        );
    },
    $container->get('globFiles')['listenerProviders']
);

if (empty($emitter->listeners)) {
    foreach ($providers as $provider) {
        $emitter->useListenerProvider(new $provider($container));
    }
}
