<?php

declare(strict_types=1);

namespace Rajtik76\QrCodePhp\Enum;

/**
 * QR code header type.
 *
 * SPD - Short Payment Descriptor (payment orders, immediate payment requests)
 * SCD - Short Collection Descriptor (collection consents / direct debits)
 */
enum HeaderType: string
{
    case SPD = 'SPD';
    case SCD = 'SCD';
}
