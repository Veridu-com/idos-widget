<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types = 1);

namespace App\Exception\SSO;

/**
 * Provider Not Found Exception.
 *
 * @see App\Exception\AppException
 */
class NotFound extends AppException {
    /**
     * {@inheritdoc}
     */
    protected $code = 404;
    /**
     * {@inheritdoc}
     */
    protected $message = 'Provider not found.';
}
