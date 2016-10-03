<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Handler;

use App\Command\SSO as SSOCommand;
use App\Event;
use App\Exception\BadRequest;
use App\Validator\ValidatorInterface;
use Interop\Container\ContainerInterface;
use League\Event\Emitter;
use Psr\Http\Message\RequestInterface;
use Respect\Validation\Validator;
use Slim\Flash\Messages;
use Slim\Http\Request;
use Slim\Interfaces\RouterInterface;
use idOS\SDK as idosSDK;

/**
 * Handles SSO commands.
 */
class SSO implements HandlerInterface {
    /**
     * Tokens array.
     * 
     * @var array
     */
    private $tokens;

    /**
     * Flash storage.
     * 
     * @var Slim\Flash\Messages
     */
    private $flash;

    /**
     * idOS SDK.
     * 
     * @var idOS\SDK
     */
    private $idosSDK;

    /**
     * Slim Router.
     * 
     * Slim\Router
     */
    private $router;

    /**
     * Slim Request.
     * 
     * \Slim\Http\Request
     */
    private $request;

    /**
     * OAuth config array.
     * 
     * @var array
     */
    private $OAuthConfig;

    /**
     * {@inheritdoc}
     */
    public static function register(ContainerInterface $container) {
        $container[self::class] = function (ContainerInterface $container) {
            return new \App\Handler\SSO(
                $container->get('tokens'),
                $container->get('validatorFactory')->create('SSO'),
                $container->get('flash'),
                $container->get('idosSDK'),
                $container->get('eventEmitter'),
                $container->get('router'),
                $container->get('request')
            );
        };
    }

    /**
     * Class constructor.
     *
     * @param App\Repository\SSOInterface
     * @param App\Validator\SSO
     *
     * @return void
     */
    public function __construct(array $tokens, ValidatorInterface $validator, Messages $flash, idosSDK $idosSDK, Emitter $emitter, RouterInterface $router, RequestInterface $request) {
        $this->tokens    = $tokens;
        $this->validator = $validator;
        $this->flash     = $flash;
        $this->idosSDK   = $idosSDK;
        $this->emitter   = $emitter;
        $this->router    = $router;
        $this->request   = $request;
    }

    /**
     * Handles user login.
     *
     * @param App\Command\SSO\Login $command
     *
     * @return string The redirect URL
     */
    public function handleLogin(SSOCommand\Login $command) : string {
        $this->validator->assertProvider($command->provider, $this->tokens);
        // assert if $command->credentialPubKey exists in idOS

        $this->flash->addMessage('credentialPubKey', $command->credentialPubKey);
        $this->setOAuthConfig($command->provider, $command->credentialPubKey);

        $this->emitter->emit(new Event\LoginStarted($command->provider, $command->credentialPubKey));

        return $this->getCallbackUrl();
    }

    /**
     * Handles callback from providers.
     *
     * @param App\Command\SSO\Callback $command
     *
     * @return string The redirect URL
     */
    public function handleCallback(SSOCommand\Callback $command) : array {
        $this->validator->assertProvider($command->provider, $this->tokens);
        $this->validator->assertFlashedPubKey($command->flashedCredentialPubKey);

        $credentialPubKey = $command->flashedCredentialPubKey[0];

        // assert if $command->credentialPubKey exists in idOS
        $this->setOAuthConfig($command->provider, $credentialPubKey);

        switch ($this->OAuthConfig['OAUTH_VERSION']) {
            case 1:
                $tokens = $this->handleCallbackOAuth1($command->queryParams);
                break;

            case 2:
                $this->validator->assertFlashedState($command->flashedState);
                $state = $command->flashedState[0];

                $tokens = $this->handleCallbackOAuth2($command->queryParams, $state);
                break;
        }

        $response = $this->idosSDK
            ->Sso
            ->createNew($command->provider, $credentialPubKey, $tokens['token'], $tokens['secret'] ?? '');

        if (! $response['data']) {
            $this->emitter->emit(new Event\LoginFailed($command->provider, $credentialPubKey));
            throw new ProcessNotStarted();
        }

        $this->emitter->emit(new Event\LoginSucceeded($command->provider, $credentialPubKey));

        return $response['data'];
    }

    /**
     * Handles OAuth v 1 callback. 
     *
     * @param array $queryParams The query parameters
     *
     * @throws Exception\Social\LoginFailed
     *
     * @return array Access tokens
     */
    private function handleCallbackOAuth1(array $queryParams) : array {
        if (isset($queryParams['oauth_token'], $queryParams['oauth_verifier'])) {
            $access = $this->OAuthConfig['storage']->retrieveAccessToken($this->OAuthConfig['provider']->service());
            $access = $this->OAuthConfig['provider']->requestAccessToken($queryParams['oauth_token'], $queryParams['oauth_verifier'], $access->getRequestTokenSecret());

            return [
                'token'  => $access->getAccessToken(),
                'secret' => $access->getAccessTokenSecret()
            ];
        }

        //login failed
        if (! empty($queryParams['error_description']))
            $error = htmlentities(urldecode($queryParams['error_description']));
        elseif (! empty($queryParams['error']['message']))
            $error = htmlentities(urldecode($queryParams['error']['message']));

        throw new App\Exception\LoginFailed($error);
    }

    /**
     * Handles OAuth2 callback requests.
     *
     * @param array  $queryParams The query parameters
     * @param string $state       The state
     *
     * @throws App\Exception\Social\InvalidRequest (description)
     * @throws Exception\Social\LoginFailed        (description)
     *
     * @return <type> ( description_of_the_return_value )
     */
    private function handleCallbackOAuth2(array $queryParams, $state) : array {
        if (isset($queryParams['code'], $queryParams['state'])) {
            if ($queryParams['state'] !== $state)
                throw new BadRequest();
            $access = $this->OAuthConfig['provider']->requestAccessToken($queryParams['code']);

            return [
                'token'   => $access->getAccessToken(),
                'refresh' => $access->getRefreshToken()
            ];
        }

        //login failed
        if (! empty($queryParams['error_description']))
            $error = htmlentities(urldecode($queryParams['error_description']));
        elseif (! empty($queryParams['error']['message']))
            $error = htmlentities(urldecode($queryParams['error']['message']));
        elseif ((! empty($queryParams['error'])) && (! is_array($queryParams['error'])))
            $error = 'Provider error: ' . htmlentities(urldecode($queryParams['error']));

        throw new Exception\Social\LoginFailed($error);
    }

    /**
     * Set OAuth configuration.
     *
     * @param string $provider The provider
     */
    private function setOAuthConfig(string $providerName, string $credentialPubKey) {
        // @FIXME
        // load customer configuration here
        $providerTokens = $this->tokens[$providerName];

        $uriObject = $this->request->getUri();

        $baseUrl = $uriObject->getScheme() . '://' . $uriObject->getHost() . ( $uriObject->getPort() != 80 ? ':' . $uriObject->getPort() : '');        
        $uri = $this->router->pathFor('sso:callback', ['provider' => 'facebook']);
        $callbackUrl = $baseUrl . $uri;

        $config = [
            'key'      => $providerTokens['sso_key'] ?? $providerTokens['key'] ?? '',
            'secret'   => $providerTokens['sso_secret'] ?? $providerTokens['secret'] ?? '',
            'callback' => $callbackUrl,
            'scope'    => $providerTokens['sso_scope'] ?? $providerTokens['scope'] ?? [],
            'options'  => $providerTokens['options'] ?? [],
            'version'  => $providerTokens['version'] ?? '',
        ];

        $storage     = new \OAuth\Common\Storage\Session();
        $credentials = new \OAuth\Common\Consumer\Credentials($config['key'], $config['secret'], $config['callback']);
        $service     = new \OAuth\ServiceFactory();
        $client      = new \OAuth\Common\Http\Client\CurlClient();
        $client->setCurlParameters([\CURLOPT_ENCODING => '']);
        $service->setHttpClient($client);
        $provider = $service->createService($providerName, $credentials, $storage, $config['scope'], null, $config['version']);

        $this->OAuthConfig = array_merge($config,
            [
                'providerName'  => $provider,
                'storage'       => $storage,
                'client'        => $client,
                'service'       => $service,
                'provider'      => $provider,
                'OAUTH_VERSION' => $provider::OAUTH_VERSION
            ]
        );
        // set flash of callback url
    }

    private function getCallbackUrl() {
        switch ($this->OAuthConfig['OAUTH_VERSION']) {
            case 1:
                $url = $this->getOAuthUrl([]);
                break;
            case 2:
                $state = sha1(mt_rand(0, 10000) . $_SERVER['REMOTE_ADDR']);
                $this->flash->addMessage('state', $state);
                $url = $this->getOAuthUrl(['state' => $state]);
                break;
        }

        return $url->getAbsoluteUri();
    }

    private function getOAuthUrl($setup = []) {
        $provider = $this->OAuthConfig['provider'];

        switch ($this->OAuthConfig['OAUTH_VERSION']) {
            case 1:
                $token = $provider->requestRequestToken();

                return $provider->getAuthorizationUri(array_merge(
                    ['oauth_token' => $token->getRequestToken()],
                    $this->OAuthConfig['options'],
                    $setup
                ));
            case 2:
                return $provider->getAuthorizationUri(array_merge(
                    $this->OAuthConfig['options'],
                    $setup
                ));
            default:
                return;
        }
    }

}
