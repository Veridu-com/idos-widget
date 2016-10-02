<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Exception;

/**
 * Bad Request Exception.
 *
 * @see App\Exception\AppException
 */
class BadRequest extends AppException {
    /**
     * {@inheritdoc}
     */
    protected $code = 400;
    /**
     * {@inheritdoc}
     */
    protected $message = 'Bad request.';
}
