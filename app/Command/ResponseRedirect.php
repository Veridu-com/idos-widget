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
class ResponseRedirect extends AbstractCommand {
    /**
     * Response instance.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    public $response;

    /**
     * Command url.
     *
     * @var string
     */
    public $url;

    /**
     * HTTP Status Code.
     *
     * @var int
     */
    public $statusCode = 302;

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters) {

        return $this;
    }
}
