<?php

declare(strict_types=1);

namespace Rajtik76\QrCodePhp\Exception;

use InvalidArgumentException;

/**
 * Thrown when a field value fails SPAYD specification validation.
 */
class ValidationException extends InvalidArgumentException
{
}
