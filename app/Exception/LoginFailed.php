<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Exception;

/**
 * Login failed Exception.
 *
 * @see App\Exception\AppException
 */
class LoginFailed extends AppException {
    /**
     * {@inheritdoc}
     */
    protected $code = 401;
    /**
     * {@inheritdoc}
     */
    protected $message = 'Login failed.';
}
