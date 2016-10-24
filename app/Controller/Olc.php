<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Exception\NotFound;
use App\Factory\Command;
use League\Tactician\CommandBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use idOS\Auth\StringToken;
use idOS\SDK;

class Olc implements ControllerInterface {
    /**
     * Command Bus instance.
     *
     * @var \League\Tactician\CommandBus
     */
    private $commandBus;
    /**
     * Command Factory instance.
     *
     * @var App\Factory\Command
     */
    private $commandFactory;

    /**
     * Class constructor.
     *
     * @param \League\Tactician\CommandBus $commandBus
     * @param App\Factory\Command          $commandFactory
     * @param Slim\Flash\Messages          $flash
     *
     * @return void
     */
    public function __construct(
        CommandBus $commandBus,
        Command $commandFactory,
        SDK $idosSDK
    ) {
        $this->commandBus     = $commandBus;
        $this->commandFactory = $commandFactory;
        $this->idosSDK = $idosSDK;
    }

    /**
     * Retrieves one widget with its configuration.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getOne(ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
        $widgetHash       = $request->getAttribute('widgetHash');
        
        $apiResponse = $this->idosSDK->Company('veridu-ltd')->widgets->getOne($widgetHash);

        if (empty($apiResponse['data'])) {
            throw new NotFound;
        }

        $widget = $apiResponse['data'];


        $queryParams = $request->getQueryParams();

        $config = $widget['config'];
        $preferences = $config['preferences'] ?? null;

        if ($preferences) {
            $preferences['selector']  = $queryParams['selector'] ?? '#idos-embedded-widget';
        }

        $body = [
            'window' => [
                'variable' => 'IDOS_EMBEDDED_WIDGET_CONFIG',
                'data' => [
                    'version' => __VERSION__,
                    'widget' => [
                        'credential' => $widget['credential']
                    ],
                    'preferences' => $preferences ?? null,
                    'providers'   => $config['providers'] ?? null
                ],
            ],
            'script' =>  file_get_contents(__DIR__ . '../../../resources/embedded-widget.js')
        ];

        $command = $this->commandFactory->create('OlcResponse');
        $command
            ->setParameter('request', $request)
            ->setParameter('response', $response)
            ->setParameter('body', $body);

        return $this->commandBus->handle($command);
    }

}
