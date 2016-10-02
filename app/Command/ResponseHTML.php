<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Command;

/**
 * Response Redirect Command.
 */
class ResponseHTML extends AbstractCommand {
    /**
     * View path (dotted path root on /views).
     *
     * @var string
     */
    public $viewPath;

    /**
     * View parameters.
     *
     * @var array
     */
    public $viewParams;

    /**
     * Response instance.
     *
     * @var Slim\Http\Response
     */
    public $response;

    /**
     * HTTP Status Code.
     *
     * @var int
     */
    public $statusCode = 200;

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters) {

        return $this;
    }
}
