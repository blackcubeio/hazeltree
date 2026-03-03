<?php

declare(strict_types=1);

/**
 * InvalidTreeSegmentException.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Hazeltree\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a tree segment is invalid (must be > 0).
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
class InvalidSegmentException extends RuntimeException
{
    public function __construct(string $message = 'Tree segment must be greater than 0', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
