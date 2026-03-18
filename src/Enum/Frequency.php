<?php

declare(strict_types=1);

namespace Rajtik76\QrCodePhp\Enum;

/**
 * Standing order / collection frequency (FRQ field).
 */
enum Frequency: string
{
    case Daily = '1D';
    case Monthly = '1M';
    case Quarterly = '3M';
    case SemiAnnually = '6M';
    case Annually = '1Y';
}
