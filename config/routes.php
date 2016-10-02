<?php
/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

if (! isset($app)) {
    die('$app is not set!');
}

$routes = $app->getContainer()->globFiles['routes'];

$app->group(
    '/' . __VERSION__,
    function () use ($routes) {
        foreach ($routes as $file) {
            if (preg_match('/(Abstract|Interface)/', $file)) {
                continue;
            }

            $className = sprintf('\\App\\Route\\%s', str_replace('.php', '', basename($file)));

            if (class_exists($className)) {
                $className::register($this);
            }
        }
    }
);
