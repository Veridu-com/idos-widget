<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Handler;

use App\Command\CommandInterface;
use App\Command\Widget as WidgetCommand;
use App\Event;
use App\Exception;
use App\Exception\BadRequest;
use App\Exception\SourceNotFound;
use App\Extension\CreateHTMLResponse;
use App\Factory\Command as CommandFactory;
use App\Validator\ValidatorInterface;
use Interop\Container\ContainerInterface;
use League\Event\Emitter;
use League\Tactician\CommandBus;
use Psr\Http\Message\RequestInterface;
use Respect\Validation\Validator;
use Slim\Flash\Messages;
use Slim\Http\Request;
use Slim\Interfaces\RouterInterface;
use idOS\Auth\CredentialToken;
use idOS\Auth\StringToken;
use idOS\SDK as idosSDK;

/**
 * Handles Widget commands.
 */
class Widget implements HandlerInterface {
    use CreateHTMLResponse;

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
     * idOS Credentials.
     * 
     * @var array
     */
    private $idosCredentials;

    /**
     * {@inheritdoc}
     */
    public static function register(ContainerInterface $container) {
        $container[self::class] = function (ContainerInterface $container) {
            return new \App\Handler\Widget(
                $container->get('tokens'),
                $container->get('validatorFactory')->create('Widget'),
                $container->get('flash'),
                $container->get('idosSDK'),
                $container->get('eventEmitter'),
                $container->get('router'),
                $container->get('request'),
                $container->get('commandBus'),
                $container->get('commandFactory'),
                $container->get('idosCredentials')
            );
        };
    }

    /**
     * Class constructor.
     *
     * @param array                              $tokens         The tokens
     * @param \App\Validator\ValidatorInterface  $validator      The validator
     * @param \Slim\Flash\Messages               $flash          The flash
     * @param \idosSDK                           $idosSDK        The idos sdk
     * @param \League\Event\Emitter              $emitter        The emitter
     * @param \Slim\Interfaces\RouterInterface   $router         The router
     * @param \Psr\Http\Message\RequestInterface $request        The request
     * @param \League\Tactician\CommandBus       $commandBus     The command bus
     * @param \App\Factory\Command               $commandFactory The command factory
     */
    public function __construct(array $tokens, ValidatorInterface $validator, Messages $flash, idosSDK $idosSDK, Emitter $emitter, RouterInterface $router, RequestInterface $request, CommandBus $commandBus, CommandFactory $commandFactory, array $idosCredentials) {
        $this->tokens            = $tokens;
        $this->validator         = $validator;
        $this->flash             = $flash;
        $this->idosSDK           = $idosSDK;
        $this->emitter           = $emitter;
        $this->router            = $router;
        $this->request           = $request;
        $this->commandBus        = $commandBus;
        $this->commandFactory    = $commandFactory;
        $this->idosCredentials   = $idosCredentials;
    }

    /**
     * Handles user login.
     *
     * @param App\Command\Widget\SSO $command
     *
     * @return mixed string The redirect URL | \Psr\Http\Message\ResponseInterface The HTML error response
     */
    public function handleSSO(WidgetCommand\SSO $command) {
        $this->flash->addMessage('callee', 'sso');
        $this->setSdkUrl($command->queryParams['apiUrl'] ?? null);

        try {
            $this->validator->assertSource($command->provider, $this->tokens);
        } catch(SourceNotFound $e) {
            return $this->createHTMLResonse($command->response, $this->commandBus, $this->commandFactory, 'error.provider-not-found', ['message' => $e->getMessage()]);
        }

        $this->populateFlashedSession($command);
        $this->setOAuthConfig($command->provider, $command->companySlug, $command->credentialPubKey);

        $this->emitter->emit(new Event\LoginStarted($command->provider, $command->credentialPubKey, 'sso'));

        return $this->getCallbackUrl();
    }

    /**
     * Handles user login.
     *
     * @param App\Command\Widget\OAuth $command
     *
     * @return mixed string The redirect URL | \Psr\Http\Message\ResponseInterface The HTML error response
     */
    public function handleOAuth(WidgetCommand\OAuth $command) {
        $this->flash->addMessage('callee', 'oauth');
        $this->setSdkUrl($command->queryParams['apiUrl'] ?? null);

        $userToken = $command->queryParams['userToken'] ?? null;

        try {
            $this->validator->assertSource($command->provider, $this->tokens);
        } catch(SourceNotFound $e) {
            return $this->createHTMLResonse($command->response, $this->commandBus, $this->commandFactory, 'error.provider-not-found', ['message' => $e->getMessage()]);
        }

        $this->validator->assertToken($userToken);

        $this->populateFlashedSession($command);
        $this->flash->addMessage('userToken', $userToken);

        $this->setOAuthConfig($command->provider, $command->companySlug, $command->credentialPubKey);

        $this->emitter->emit(new Event\LoginStarted($command->provider, $command->credentialPubKey, 'oath'));

        return $this->getCallbackUrl();
    }

    /**
     * Populates  the flashed session.
     * There is a diff between SSO and OAuth methods,
     * different parameters are set within the functions.
     *
     * @param \App\Command\CommandInterface  $command  The command
     * 
     * @return void
     */
    private function populateFlashedSession(CommandInterface $command) {        
        $this->flash->addMessage('credentialPubKey', $command->credentialPubKey);
        $this->flash->addMessage('companySlug', $command->companySlug);
        $this->flash->addMessage('apiUrl', $command->queryParams['apiUrl'] ?? null);

        $this->setSdkUrl($command->queryParams['apiUrl'] ?? null);

        if (isset($command->queryParams['signupHash'])) {
            $this->flash->addMessage('signupHash', $command->queryParams['signupHash']);
        }
    }

    /**
     * Handles callback from providers.
     *
     * @param App\Command\Widget\Callback $command
     *
     * @return string The redirect URL
     */
    public function handleCallback(WidgetCommand\Callback $command) : array {
        $flashedCompanySlug      = $this->flash->getMessage('companySlug');
        $flashedCredentialPubKey = $this->flash->getMessage('credentialPubKey');
        $flashedHash             = $this->flash->getMessage('signupHash');
        $apiUrl                  = $this->flash->getMessage('apiUrl')[0];
        $callee                  = $this->flash->getMessage('callee')[0];

        $this->setSdkUrl($apiUrl);

        try {
            $this->validator->assertSource($command->provider, $this->tokens);
        } catch(SourceNotFound $e) {
            return $this->createHTMLResonse($command->response, $this->commandBus, $this->commandFactory, 'error.provider-not-found', ['message' => $e->getMessage()]);
        }

        $this->validator->assertFlashedPubKey($flashedCredentialPubKey);

        $signupHash = null;
        if (! empty($flashedHash)) {
            $signupHash = $flashedHash[0];
        }

        $credentialPubKey = $flashedCredentialPubKey[0];
        $companySlug      = $flashedCompanySlug[0];

        // assert if $command->credentialPubKey exists in idOS
        $this->setOAuthConfig($command->provider, $companySlug, $credentialPubKey);

        switch ($this->OAuthConfig['OAUTH_VERSION']) {
            case 1:
                $tokens = $this->handleCallbackOAuth1($command->queryParams);
                break;

            case 2:
                $flashedState = $this->flash->getMessage('state');
                $this->validator->assertFlashedState($flashedState);
                $state = $flashedState[0];

                $tokens = $this->handleCallbackOAuth2($command->queryParams, $state);
                break;
        }

        switch (true) {
            case $callee === 'sso':
                // create a sso register
                $response = $this->idosSDK
                    ->Sso
                    ->createNew($command->provider, $credentialPubKey, $tokens['token'], $tokens['secret'] ?? '', $signupHash ?? '');

                if (empty($response['data'])) {
                    $this->emitter->emit(new Event\LoginFailed($command->provider, $credentialPubKey, 'sso'));
                    throw new Exception\ProcessNotStarted($response['error']['message'] ?? 'Process failed to start.');
                }

                $userTokens = $response['data'];

                break;

            case $callee === 'oauth':
                $token       = $this->flash->getMessage('userToken')[0];
                $stringToken = new StringToken('userToken', $token);
                $this->idosSDK->setAuth($stringToken);
                $sourceResource = $this->idosSDK->profile('_self')->sources;

                $response = $sourceResource->createNew(
                    $command->provider, [
                    'access_token' => $tokens['token'],
                    'token_secret' => $tokens['secret'] ?? null
                    ]
                );

                if (empty($response['data'])) {
                    $this->emitter->emit(new Event\LoginFailed($command->provider, $credentialPubKey, 'oauth'));
                    throw new Exception\ProcessNotStarted($response['error']['message']);
                }

                $userTokens = [
                    'user_token' => $token
                ];
                break;
        }

        $this->emitter->emit(new Event\LoginSucceeded($command->provider, $credentialPubKey, $callee));

        return $userTokens;
    }

    /**
     * Handles OAuth v 1 callback.
     *
     * @param array $queryParams The query parameters
     *
     * @throws \App\Exception\LoginFailed
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

        throw new Exception\LoginFailed($error);
    }

    /**
     * Handles OAuth2 callback requests.
     *
     * @param array  $queryParams The query parameters
     * @param string $state       The state
     *
     * @throws Exception\LoginFailed
     *
     * @return array
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
            $error = 'Source error: ' . htmlentities(urldecode($queryParams['error']));

        throw new Exception\LoginFailed($error);
    }

    /**
     * Gets the provider tokens.
     *
     * @param array $settings The settings
     *
     * @return array The formatted provider tokens.
     */
    private function extractProviderTokens(array $settings) : array {
        $providerTokens = [];

        if (! count($settings)) {
            return $providerTokens;
        }

        foreach ($settings as $key => $setting) {
            $key                  = last(explode('.', $setting['property']));
            $providerTokens[$key] = $setting['value'];
        }

        return $providerTokens;
    }

    /**
     * Validate provider tokens.
     *
     * @param array $tokens The tokens
     *
     * @return bool
     */
    public function validateTokens(array $tokens) : bool {
        if (! count($tokens)) {
            return false;
        }

        foreach ($tokens as $token) {
            if (empty($token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets the sdk url.
     *
     * @param      mixed  $url    The url
     */
    private function setSdkUrl($url) : self {
        try {
            if ($url) {
                $this->validator->assertUrl($url);
                $this->idosSDK->setBaseUrl($url);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('"apiUrl" param is not a valid URL');
        }

        return $this;
    }

    /**
     * Set OAuth configuration.
     *
     * @param string $provider The provider
     */
    private function setOAuthConfig(string $providerName, string $companySlug, string $credentialPubKey) {
        $credentialToken = new CredentialToken($credentialPubKey, $this->idosCredentials['public'], $this->idosCredentials['private']);
        $this->idosSDK->setAuth($credentialToken);

        // try api key settings
        $response = $this->idosSDK
            ->Company($companySlug)
            ->Settings
            ->listAll([
                'section'  => 'AppTokens',
                'property' => sprintf('%s.%s.*', $credentialPubKey, $providerName)
            ]);

        if (! is_array($response) || ! isset($response['data']) || ! $response['status']) {
            $msg = sprintf('API Error : %s', ($response['error']['message'] ?? 'Error communicating with the API'));
            throw new \RuntimeException($msg);
        }

        $providerTokens = $this->extractProviderTokens($response['data']);

        // try company settings
        if (! $this->validateTokens($providerTokens)) {
            $response = $this->idosSDK
                ->Company($companySlug)
                ->Settings
                ->listAll([
                    'section'  => 'AppTokens',
                    'property' => sprintf('%s.*', $providerName)
                ]);
            $providerTokens = $this->extractProviderTokens($response['data']);
        }

        // gets default tokens
        if (! $this->validateTokens($providerTokens)) {
            $providerTokens = $this->tokens[$providerName];
        }

        $uriObject = $this->request->getUri();

        $port        = empty($uriObject->getPort()) ? '' : ':' . $uriObject->getPort();
        $baseUrl     = 'https://' . $uriObject->getHost() . $port;
        $uri         = $this->router->pathFor('widget:callback', ['provider' => $providerName]);
        $callbackUrl = $baseUrl . $uri;

        $config = [
            'key'      => $providerTokens['sso_key'] ?? $providerTokens['key'] ?? '',
            'secret'   => $providerTokens['sso_secret'] ?? $providerTokens['secret'] ?? '',
            'callback' => $callbackUrl,
            'scope'    => $providerTokens['sso_scope'] ?? $providerTokens['scope'] ?? $this->tokens[$providerName]['sso_scope'] ?? $this->tokens[$providerName]['scope'],
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

        $this->OAuthConfig = array_merge(
            $config,
            [
                'providerName'  => $provider,
                'storage'       => $storage,
                'client'        => $client,
                'service'       => $service,
                'provider'      => $provider,
                'OAUTH_VERSION' => $provider::OAUTH_VERSION
            ]
        );
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

                return $provider->getAuthorizationUri(
                    array_merge(
                        ['oauth_token' => $token->getRequestToken()],
                        $this->OAuthConfig['options'],
                        $setup
                    )
                );
            case 2:
                return $provider->getAuthorizationUri(
                    array_merge(
                        $this->OAuthConfig['options'],
                        $setup
                    )
                );
            default:
                return;
        }
    }
}
