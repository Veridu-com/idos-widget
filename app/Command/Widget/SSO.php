<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Command\Widget;

use App\Command\AbstractCommand;

/**
 * Widget "OAuth" Command.
 */
class SSO extends AbstractCommand {
    /**
     * SSO's provider.
     *
     * @var string
     */
    public $provider;

    /**
     * SSO's credentialPubKey.
     *
     * @var string
     */
    public $credentialPubKey;

    /**
     * Query params.
     *
     * @var array
     */
    public $queryParams;

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters) : self {

        return $this;
    }
}
