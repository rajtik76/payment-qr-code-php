<?php

declare(strict_types=1);

namespace Rajtik76\QrCodePhp\Enum;

/**
 * Notification channel for payment confirmation (NT field).
 *
 * SMS   - notification via SMS (P)
 * Email - notification via e-mail (E)
 */
enum NotificationType: string
{
    case SMS = 'P';
    case Email = 'E';
}
