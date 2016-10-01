<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Listener\SSO;

use App\Event;
use App\Listener;
use Interop\Container\ContainerInterface;

class ListenerProvider extends Listener\AbstractListenerProvider {
    public function __construct(ContainerInterface $container) {
        $this->events = [
            Event\LoginStarted::class => [
                new Listener\LogFiredEventListener($container->get('log')('handler'))
            ],
            Event\LoginFailed::class => [
                new Listener\LogFiredEventListener($container->get('log')('handler'))
            ],
            Event\LoginSucceeded::class => [
                new Listener\LogFiredEventListener($container->get('log')('handler'))
            ]
        ];
    }

}
