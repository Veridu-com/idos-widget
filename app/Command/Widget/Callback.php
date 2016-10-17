<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Command\Widget;

use App\Command\AbstractCommand;

/**
 * Widget "Callback" Command.
 */
class Callback extends AbstractCommand {
    /**
     * Query params.
     * 
     * @var array
     */
    public $queryParams;

    /**
     * Widget's provider.
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
