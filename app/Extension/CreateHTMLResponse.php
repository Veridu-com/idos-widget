<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Extension;

use App\Factory\Command as CommandFactory;
use League\Tactician\CommandBus;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait to create a HTML response.
 */
trait CreateHTMLResponse {
    /**
     * Creates a HTML resonse.
     *
     * @param \Psr\Http\Message\ResponseInterface $response   The response
     * @param \League\Tactician\CommandBus        $commandBus The command bus
     * @param string                              $viewPath   The view path
     * @param array                               $viewParams The view parameters
     * 
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function createHTMLResonse(ResponseInterface $response, CommandBus $commandBus, CommandFactory $commandFactory, string $viewPath, array $viewParams) : ResponseInterface
    {
        $command = $commandFactory->create('ResponseHTML');
        $command
            ->setParameter('viewPath', $viewPath)
            ->setParameter('viewParams', $viewParams)
            ->setParameter('response', $response);

        return $this->commandBus->handle($command);
    }
}
