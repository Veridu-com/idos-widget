<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

use Apix\Cache;
use App\Command;
use App\Exception\AppException;
use App\Factory;
use App\Handler; // TODO: Why not use folder identifiers instead of using so many declarations?
use App\Middleware as Middleware;
use App\Middleware\Auth;
use Interop\Container\ContainerInterface;
use Jenssegers\Optimus\Optimus;
use League\Event\Emitter;
use League\Tactician\CommandBus;
use League\Tactician\Container\ContainerLocator;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\MethodNameInflector\HandleClassNameInflector;
use League\Tactician\Logger\Formatter\ClassNameFormatter;
use League\Tactician\Logger\Formatter\ClassPropertiesFormatter;
use League\Tactician\Logger\LoggerMiddleware;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Philo\Blade\Blade;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Validation\Validator;
use Slim\HttpCache\CacheProvider;
use Whoops\Handler\PrettyPageHandler;

if (! isset($app)) {
    die('$app is not set!');
}

session_start();

$container = $app->getContainer();

// Slim Error Handling
$container['errorHandler'] = function (ContainerInterface $container) : callable {
    return function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        \Exception $exception
    ) use ($container) {
        $settings = $container->get('settings');
        $response = $container
            ->get('httpCache')
            ->denyCache($response);

        $log = $container->get('log');
        $log('Foundation')->error(
            sprintf(
                '%s [%s:%d]',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            )
        );
        $log('Foundation')->debug($exception->getTraceAsString());

        $previousException = $exception->getPrevious();
        if ($previousException) {
            $log('Foundation')->error(
                sprintf(
                    '%s [%s:%d]',
                    $previousException->getMessage(),
                    $previousException->getFile(),
                    $previousException->getLine()
                )
            );
            $log('Foundation')->debug($previousException->getTraceAsString());
        }

        if ($exception instanceof AppException) {
            $log('handler')->info(
                sprintf(
                    '%s [%s:%d]',
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                )
            );
            $log('handler')->debug($exception->getTraceAsString());

            $body = [
                'status' => false,
                'error'  => [
                    'code' => $exception->getCode(),
                    // 'type' => $exception->getType(),
                    // 'link' => $exception->getLink(),
                    'message' => $exception->getMessage(),
                ]
            ];
            if ($settings['debug']) {
                $body['error']['trace'] = $exception->getTrace();
            }

            $command = $container
                ->get('commandFactory')
                ->create('ResponseDispatch');
            $command
                ->setParameter('request', $request)
                ->setParameter('response', $response)
                ->setParameter('body', $body)
                ->setParameter('statusCode', $exception->getCode());

            return $container->get('commandBus')->handle($command);
        }

        if ($settings['debug']) {
            $prettyPageHandler = new PrettyPageHandler();
            // Add more information to the PrettyPageHandler
            $prettyPageHandler->addDataTable(
                'Request',
                [
                    'Accept Charset'  => $request->getHeader('ACCEPT_CHARSET') ?: '<none>',
                    'Content Charset' => $request->getContentCharset() ?: '<none>',
                    'Path'            => $request->getUri()->getPath(),
                    'Query String'    => $request->getUri()->getQuery() ?: '<none>',
                    'HTTP Method'     => $request->getMethod(),
                    'Base URL'        => (string) $request->getUri(),
                    'Scheme'          => $request->getUri()->getScheme(),
                    'Port'            => $request->getUri()->getPort(),
                    'Host'            => $request->getUri()->getHost()
                ]
            );

            $whoops = new Whoops\Run();
            $whoops->pushHandler($prettyPageHandler);

            return $response
                ->withStatus(500)
                ->write($whoops->handleException($exception));
        }

        $body = [
            'status' => false,
            'error'  => [
                'id'      => $container->get('logUidProcessor')->getUid(),
                'code'    => 500,
                'type'    => 'APPLICATION_ERROR',
                'link'    => null,
                'message' => 'Internal Application Error'
            ]
        ];

        $command = $container->get('commandFactory')->create('ResponseDispatch');
        $command
            ->setParameter('request', $request)
            ->setParameter('response', $response)
            ->setParameter('body', $body)
            ->setParameter('statusCode', 500);

        return $container->get('commandBus')->handle($command);
    };
};

// Slim Not Found Handler
$container['notFoundHandler'] = function (ContainerInterface $container) : callable {
    return function (
        ServerRequestInterface $request,
        ResponseInterface $response
    ) use ($container) {
        throw new \Exception('not found');
    };
};

// Slim Not Allowed Handler
$container['notAllowedHandler'] = function (ContainerInterface $container) : callable {
    return function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $methods
    ) use ($container) {
        if ($request->isOptions()) {
            return $response->withStatus(204);
        }

        throw new \Exception('notAllowedHandler');
    };
};

// Monolog Request UID Processor
$container['logUidProcessor'] = function (ContainerInterface $container) : callable {
    return new UidProcessor();
};

// Monolog Request Processor
$container['logWebProcessor'] = function (ContainerInterface $container) : callable {
    return new WebProcessor();
};

// Monolog Logger
$container['log'] = function (ContainerInterface $container) : callable {
    return function ($channel = 'API') use ($container) {
        $settings = $container->get('settings');
        $logger   = new Logger($channel);
        $logger
            ->pushProcessor($container->get('logUidProcessor'))
            ->pushProcessor($container->get('logWebProcessor'))
            ->pushHandler(new StreamHandler($settings['log']['path'], $settings['log']['level']));

        return $logger;
    };
};

// Slim HTTP Cache
$container['httpCache'] = function (ContainerInterface $container) : CacheProvider {
    return new CacheProvider();
};

// Tactician Command Bus
$container['commandBus'] = function (ContainerInterface $container) : CommandBus {
    $settings = $container->get('settings');
    $log      = $container->get('log');

    $commandPaths = glob(__DIR__ . '/../app/Command/*/*.php');
    $commands     = [];
    foreach ($commandPaths as $commandPath) {
        $matches = [];
        preg_match_all('/.*Command\/(.*)\/(.*).php/', $commandPath, $matches);

        $resource = $matches[1][0];
        $command  = $matches[2][0];

        $commands[sprintf('App\\Command\\%s\\%s', $resource, $command)] = sprintf('App\\Handler\\%s', $resource);
    }

    $commands[Command\ResponseDispatch::class] = Handler\Response::class;
    $commands[Command\OlcResponse::class]      = Handler\Response::class;
    $commands[Command\ResponseRedirect::class] = Handler\Response::class;
    $commands[Command\ResponseHTML::class]     = Handler\Response::class;

    $handlerMiddleware = new CommandHandlerMiddleware(
        new ClassNameExtractor(),
        new ContainerLocator(
            $container,
            $commands
        ),
        new HandleClassNameInflector()
    );
    if ($settings['debug']) {
        $formatter = new ClassPropertiesFormatter();
    } else {
        $formatter = new ClassNameFormatter();
    }

    return new CommandBus(
        [
            new LoggerMiddleware(
                $formatter,
                $log('CommandBus')
            ),
            $handlerMiddleware
        ]
    );
};

// App Command Factory
$container['commandFactory'] = function (ContainerInterface $container) : Factory\Command {
    return new Factory\Command();
};

// Validator Factory
$container['validatorFactory'] = function (ContainerInterface $container) : Factory\Validator {
    return new Factory\Validator();
};

// Auth Middleware
$container['authMiddleware'] = function (ContainerInterface $container) {
    return function ($authorizationRequirement) use ($container) {

        return new Auth(
            $authorizationRequirement
        );
    };
};

// Respect Validator
$container['validator'] = function (ContainerInterface $container) : Validator {
    return Validator::create();
};

// Optimus
$container['optimus'] = function (ContainerInterface $container) : Optimus {
    $settings = $container->get('settings');

    return new Optimus(
        $settings['optimus']['prime'],
        $settings['optimus']['inverse'],
        $settings['optimus']['random']
    );
};

// App files
$container['globFiles'] = function () : array {
    return [
        'routes'             => glob(__DIR__ . '/../app/Route/*.php'),
        'handlers'           => glob(__DIR__ . '/../app/Handler/*.php'),
        'listenerProviders'  => glob(__DIR__ . '/../app/Listener/*/*Provider.php'),
    ];
};

// App files
$container['tokens'] = $container->factory(
    function () {
        return require __DIR__ . '/tokens.php';
    }
);

// Registering Event Emitter
$container['eventEmitter'] = function (ContainerInterface $container) : Emitter {
    $emitter = new Emitter();

    return $emitter;
};

// Session flashing 
$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

// idOS SDK
$container['idosSDK'] = function (ContainerInterface $container) {
    $settings = $container->get('settings');
    $auth = new \idOS\Auth\None();

    $sdk = \idOS\SDK::create($auth);

    if (! empty($settings['dev']['enabled'])) {
        $sdk->setBaseUrl($settings['dev']['API_URL']);
    }

    return $sdk;
};

// Blade templates
$container['blade'] = function () {
    $views = __DIR__ . '/../resources/views';
    $cache = __DIR__ . '/../resources/views/cache';

    return new Blade($views, $cache);
};

// idOS Credentials
$container['idosCredentials'] = function () use ($container) {
    $settings = $container->get('settings');

    return $settings['idos-credentials'];
};
