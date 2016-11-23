<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Event;

/**
 * LoginStarted event.
 */
class LoginStarted extends AbstractEvent {
    /**
     * Related provider.
     *
     * @var string
     */
    public $provider;

    /**
     * Related provider.
     *
     * @var string
     */
    public $credentialPubKey;

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
