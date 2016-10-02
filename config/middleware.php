<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

use App\Middleware\Debugger;
use App\Middleware\Watcher;
use Slim\HttpCache\Cache;

if (! isset($app)) {
    die('$app is not set!');
}

$app
    ->add(new Watcher($app->getContainer()))
    ->add(new Cache('private, no-cache, no-store', 0, true))
    ->add(new Debugger());
