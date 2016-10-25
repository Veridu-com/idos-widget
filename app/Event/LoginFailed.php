<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Event;

/**
 * LoginFailed event.
 */
class LoginFailed extends AbstractEvent {
    /**
     * Related provider.
     *
     * @var string
     */
    public $provider;

    /**
     * Class constructor.
     *
     * @param string $provider         The provider
     * @param string $credentialPubKey The credential public key
     */
    public function __construct(string $provider, string $credentialPubKey) {
        $this->provider         = $provider;
        $this->credentialPubKey = $credentialPubKey;
    }
}
