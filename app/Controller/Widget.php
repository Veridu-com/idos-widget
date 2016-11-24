<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Controller;

use App\Exception\ProcessNotStarted;
use App\Extension\CreateHTMLResponse;
use App\Factory\Command;
use League\Tactician\CommandBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

class Widget implements ControllerInterface {
    use CreateHTMLResponse;

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
     * Flash storage.
     *
     * @var Slim\Flash\Messages
     */
    private $flash;

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
        Messages $flash
    ) {
        $this->commandBus     = $commandBus;
        $this->commandFactory = $commandFactory;
        $this->flash          = $flash;
    }

    /**
     * Extract oAuth tokens & redirects response to provider's url.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function sso(ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
        $provider         = $request->getAttribute('provider');
        $companySlug      = $request->getAttribute('companySlug');
        $credentialPubKey = $request->getAttribute('credentialPubKey');

        $command = $this->commandFactory->create('Widget\\SSO')
            ->setParameter('provider', $provider)
            ->setParameter('response', $response)
            ->setParameter('companySlug', $companySlug)
            ->setParameter('queryParams', $request->getQueryParams())
            ->setParameter('credentialPubKey', $credentialPubKey);

        $result = $this->commandBus->handle($command);

        if ($result instanceof ResponseInterface) {
            return $response;
        }

        $command = $this->commandFactory->create('ResponseRedirect');
        $command
            ->setParameter('response', $response)
            ->setParameter('url', $result);

        return $this->commandBus->handle($command);
    }

    /**
     * Extract oAuth tokens & redirects response to provider's url.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function oauth(ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
        $provider         = $request->getAttribute('provider');
        $companySlug      = $request->getAttribute('companySlug');
        $credentialPubKey = $request->getAttribute('credentialPubKey');

        $command = $this->commandFactory->create('Widget\\OAuth')
            ->setParameter('provider', $provider)
            ->setParameter('response', $response)
            ->setParameter('companySlug', $companySlug)
            ->setParameter('queryParams', $request->getQueryParams())
            ->setParameter('credentialPubKey', $credentialPubKey);

        $result = $this->commandBus->handle($command);

        if ($result instanceof ResponseInterface) {
            return $response;
        }

        $command = $this->commandFactory->create('ResponseRedirect');
        $command
            ->setParameter('response', $response)
            ->setParameter('url', $result);

        return $this->commandBus->handle($command);
    }

    /**
     * Receives callback from social providers.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function callback(ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
        $provider = $request->getAttribute('provider');
        $isSignup = ! empty($this->flash->getMessage('signupHash'));

        $command = $this->commandFactory->create('Widget\\Callback')
            ->setParameter('provider', $provider)
            ->setParameter('response', $response)
            ->setParameter('queryParams', $request->getQueryParams());

        try {
            $result = $this->commandBus->handle($command);

            if ($result instanceof ResponseInterface) {
                return $response;
            }

            return $this->createHTMLResonse($response, $this->commandBus, $this->commandFactory, 'login', ['tokens' => $result, 'source' => $provider]);

        } catch (ProcessNotStarted $e) {
            $viewPath = $isSignup ? 'api.signup-error' : 'api.login-error';

            $command = $this->commandFactory->create('ResponseHTML');
            $command
                ->setParameter('viewPath', $viewPath)
                ->setParameter('viewParams', ['message' => $e->getMessage()])
                ->setParameter('response', $response);

            return $this->commandBus->handle($command);
        }

    }
}
