<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Listener;

use League\Event\ListenerAcceptorInterface;
use League\Event\ListenerProviderInterface;

abstract class AbstractListenerProvider implements ListenerProviderInterface {
    /**
     * Associative array defining events and their listeners
     * initialized on constructor.
     *
     * @format array [ 'event' => [ 'listener1', 'listener2'] ]
     */
    protected $events = [];

    public function provideListeners(ListenerAcceptorInterface $acceptor) {
        foreach ($this->events as $eventName => $listeners) {
            if (sizeof($listeners)) {
                foreach ($listeners as $listener) {
                    $acceptor->addListener($eventName, $listener);
                }
            }
        }
    }

}
