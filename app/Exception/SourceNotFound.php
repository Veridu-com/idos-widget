<?php

/*
 * Copyright (c) 2012-2016 Veridu Ltd <https://veridu.com>
 * All rights reserved.
 */

declare(strict_types=1);

namespace App\Exception;

/**
 * Source Not Found Exception.
 *
 * @see App\Exception\AppException
 */
class SourceNotFound extends AppException {
    /**
     * {@inheritdoc}
     */
    protected $code = 404;
    /**
     * {@inheritdoc}
     */
    protected $message = 'Source not found.';
}
