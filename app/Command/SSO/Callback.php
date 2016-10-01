<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Command\SSO;

use App\Command\AbstractCommand;

/**
 * SSO "Callback" Command.
 */
class Callback extends AbstractCommand {
    /**
     * Query params.
     * 
     * @var array
     */
    public $queryParams;

    /**
     * SSO's credential public key.
     *
     * @var string
     */
    public $flashedCredentialPubKey;

    /**
     * SSO's state.
     *
     * @var string
     */
    public $flashedState;

    /**
     * SSO's provider.
     *
     * @var string
     */
    public $provider;

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters) : self {

        return $this;
    }
}
