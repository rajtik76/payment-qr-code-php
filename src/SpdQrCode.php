<?php

declare(strict_types=1);

namespace Rajtik76\QrCodePhp;

use DateTime;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Color\ColorInterface;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Encoding\EncodingInterface;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\LabelInterface;
use Endroid\QrCode\Logo\LogoInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Rajtik76\QrCodePhp\Enum\Frequency;
use Rajtik76\QrCodePhp\Enum\HeaderType;
use Rajtik76\QrCodePhp\Enum\NotificationType;
use Rajtik76\QrCodePhp\Exception\ValidationException;

/**
 * Generates a SPAYD (Short Payment Descriptor) QR code string.
 *
 * Compliant with the Czech Banking Association standard v1.2 (effective 2022-01-01).
 * Specification: "Standardy bankovních aktivit – Formát pro sdílení platebních údajů
 * v rámci tuzemského platebního styku v ČZK prostřednictvím QR kódů", version 1.2.
 *
 * Output format: {HEADER}*{VERSION}*{KEY}:{VALUE}*{KEY}:{VALUE}*...
 * Fields are emitted in alphabetical key order (which is also the canonical order
 * required for the optional CRC32 checksum).
 *
 * Usage:
 *   $qr = new SpdQrCode(
 *       acc: 'CZ3301000000000002970297',
 *       am: '555.55',
 *       msg: 'PLATBA ZA ELEKTRINU',
 *   );
 *   echo $qr->generate();
 */
class SpdQrCode
{
    private const VERSION = '1.0';

    /**
     * @param string              $acc        REQUIRED. Recipient IBAN, optionally followed by '+' and BIC/SWIFT.
     *                                        Example: 'CZ3301000000000002970297' or 'CZ3301000000000002970297+GIBACZPX'.
     *                                        Max 46 characters total.
     * @param HeaderType          $headerType SPD (payment order) or SCD (collection consent). Default: SPD.
     * @param string|null         $altAcc     Alternative recipient accounts, comma-separated IBAN[+BIC] pairs.
     *                                        Max 93 characters, max 2 accounts recommended.
     * @param string|null         $am         Payment amount. Decimal with at most 2 decimal places, range 0–9 999 999.99.
     *                                        Max 10 characters. Example: '555.55'.
     * @param string|null         $cc         ISO 4217 currency code. Only 'CZK' is currently permitted.
     *                                        Defaults to 'CZK'. Pass null to omit the field.
     * @param bool|null           $dh         Death handling: false = continue payments (DH:0),
     *                                        true = stop payments (DH:1), null = field omitted.
     * @param string|null         $dl         End/expiry date in YYYYMMDD format (ISO 8601).
     *                                        For standing orders: expiry date. For SCD: collection end date.
     * @param string|null         $dt         Due date in YYYYMMDD format (ISO 8601).
     *                                        For standing orders: first payment date.
     * @param Frequency|null      $frq        Payment frequency for standing orders / collection consents.
     * @param string|null         $msg        Message for the recipient (or standing order name).
     *                                        Max 60 characters. Must not contain '*'.
     * @param NotificationType|null $nt       Notification channel: SMS or Email.
     * @param string|null         $nta        Notification address. Required when $nt is set.
     *                                        Phone (NT=SMS): digits and leading '+' only, max 320 chars.
     *                                        E-mail (NT=Email): valid RFC address, max 319 chars.
     * @param string|null         $pt         Payment type. 'IP' requests an instant payment. Max 3 characters.
     * @param string|null         $rf         Payment reference number (digits only). Max 16 characters.
     * @param string|null         $rn         Recipient name. Max 35 characters. Must not contain '*'.
     * @param bool                $withCrc32  When true, appends a CRC32 checksum field to the output.
     * @param string|null         $xId        Payer's internal payment identifier. Max 20 characters.
     * @param string|null         $xKs        Constant symbol (digits only). Max 10 digits.
     * @param int|null            $xPer       Retry period in days for failed payments (1–30).
     * @param string|null         $xSelf      Message for the payer's own use. Max 60 characters.
     * @param string|null         $xSs        Specific symbol (digits only). Max 10 digits.
     * @param string|null         $xUrl       Custom URL for payer use. Max 140 characters.
     * @param string|null         $xVs        Variable symbol (digits only). Max 10 digits.
     *
     * @throws ValidationException When any field value violates the specification.
     */
    public function __construct(
        private readonly string $acc,
        private readonly HeaderType $headerType = HeaderType::SPD,
        private readonly ?string $altAcc = null,
        private readonly ?string $am = null,
        private readonly ?string $cc = 'CZK',
        private readonly ?bool $dh = null,
        private readonly ?string $dl = null,
        private readonly ?string $dt = null,
        private readonly ?Frequency $frq = null,
        private readonly ?string $msg = null,
        private readonly ?NotificationType $nt = null,
        private readonly ?string $nta = null,
        private readonly ?string $pt = null,
        private readonly ?string $rf = null,
        private readonly ?string $rn = null,
        private readonly bool $withCrc32 = false,
        private readonly ?string $xId = null,
        private readonly ?string $xKs = null,
        private readonly ?int $xPer = null,
        private readonly ?string $xSelf = null,
        private readonly ?string $xSs = null,
        private readonly ?string $xUrl = null,
        private readonly ?string $xVs = null,
    ) {
        $this->validate();
    }

    /**
     * Returns the string representation of the QR code.
     * This is equivalent to calling $qr->generate().
     *
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->generate();
    }

    /**
     * Generates and returns the SPAYD QR code string.
     *
     * Fields are emitted in alphabetical key order.
     * If $withCrc32 is true, a CRC32 checksum field is appended.
     *
     * @return non-empty-string
     */
    public function generate(): string
    {
        $parts = [$this->headerType->value, self::VERSION];

        foreach ($this->buildFields() as [$key, $value]) {
            $parts[] = $key . ':' . $this->encodeValue($value);
        }

        $output = implode('*', $parts) . '*';

        if ($this->withCrc32) {
            $output .= 'CRC32:' . $this->calculateCrc32($output) . '*';
        }

        return $output;
    }

    /**
     * Generates a PNG QR code image from the SPAYD string and returns it as a data URI.
     *
     * All parameters mirror the {@see QrCode} constructor exactly (minus {@code $data},
     * which is always the output of {@see generate()}), plus the optional {@code $logo}
     * and {@code $label} accepted by {@see PngWriter::write()}.
     *
     * When called with no arguments every default matches the QrCode constructor default:
     *   – encoding        UTF-8
     *   – errorCorrectionLevel  Low
     *   – size            300 px
     *   – margin          10 px
     *   – roundBlockSizeMode    Margin
     *   – foregroundColor black  (0, 0, 0)
     *   – backgroundColor white  (255, 255, 255)
     *
     * @param EncodingInterface        $encoding             Character encoding (default UTF-8).
     * @param ErrorCorrectionLevel     $errorCorrectionLevel QR error-correction capacity (default Low).
     * @param int                      $size                 Target image dimension in pixels (default 300).
     * @param int                      $margin               Border around the QR code in pixels (default 10).
     * @param RoundBlockSizeMode       $roundBlockSizeMode   How fractional block sizes are rounded (default Margin).
     * @param ColorInterface           $foregroundColor      QR module colour (default black).
     * @param ColorInterface           $backgroundColor      Background colour (default white).
     * @param LogoInterface|null       $logo                 Optional logo to embed in the centre.
     * @param LabelInterface|null      $label                Optional text label below the QR code.
     *
     * @return string Data URI: "data:image/png;base64,{base64-encoded PNG}"
     */
    public function generateBase64(
        EncodingInterface $encoding = new Encoding('UTF-8'),
        ErrorCorrectionLevel $errorCorrectionLevel = ErrorCorrectionLevel::Low,
        int $size = 300,
        int $margin = 10,
        RoundBlockSizeMode $roundBlockSizeMode = RoundBlockSizeMode::Margin,
        ColorInterface $foregroundColor = new Color(0, 0, 0),
        ColorInterface $backgroundColor = new Color(255, 255, 255),
        ?LogoInterface $logo = null,
        ?LabelInterface $label = null,
    ): string {
        $qrCode = new QrCode(
            data: $this->generate(),
            encoding: $encoding,
            errorCorrectionLevel: $errorCorrectionLevel,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: $roundBlockSizeMode,
            foregroundColor: $foregroundColor,
            backgroundColor: $backgroundColor,
        );

        return new PngWriter()->write($qrCode, $logo, $label)->getDataUri();
    }

    /**
     * Returns all field key–value pairs in alphabetical key order.
     *
     * This order is also the canonical order required by the CRC32 specification.
     *
     * @return list<array{string, string}>
     */
    private function buildFields(): array
    {
        $fields = [];

        // ACC is mandatory and always first (A)
        $fields[] = ['ACC', $this->acc];

        if ($this->altAcc !== null) {
            $fields[] = ['ALT-ACC', $this->altAcc];
        }

        if ($this->am !== null) {
            $fields[] = ['AM', $this->am];
        }

        if ($this->cc !== null) {
            $fields[] = ['CC', $this->cc];
        }

        if ($this->dh !== null) {
            $fields[] = ['DH', $this->dh ? '1' : '0'];
        }

        if ($this->dl !== null) {
            $fields[] = ['DL', $this->dl];
        }

        if ($this->dt !== null) {
            $fields[] = ['DT', $this->dt];
        }

        if ($this->frq !== null) {
            $fields[] = ['FRQ', $this->frq->value];
        }

        if ($this->msg !== null) {
            $fields[] = ['MSG', $this->msg];
        }

        if ($this->nt !== null) {
            $fields[] = ['NT', $this->nt->value];
        }

        if ($this->nta !== null) {
            $fields[] = ['NTA', $this->nta];
        }

        if ($this->pt !== null) {
            $fields[] = ['PT', $this->pt];
        }

        if ($this->rf !== null) {
            $fields[] = ['RF', $this->rf];
        }

        if ($this->rn !== null) {
            $fields[] = ['RN', $this->rn];
        }

        if ($this->xId !== null) {
            $fields[] = ['X-ID', $this->xId];
        }

        if ($this->xKs !== null) {
            $fields[] = ['X-KS', $this->xKs];
        }

        if ($this->xPer !== null) {
            $fields[] = ['X-PER', (string) $this->xPer];
        }

        if ($this->xSelf !== null) {
            $fields[] = ['X-SELF', $this->xSelf];
        }

        if ($this->xSs !== null) {
            $fields[] = ['X-SS', $this->xSs];
        }

        if ($this->xUrl !== null) {
            $fields[] = ['X-URL', $this->xUrl];
        }

        if ($this->xVs !== null) {
            $fields[] = ['X-VS', $this->xVs];
        }

        return $fields;
    }

    /**
     * Encodes a field value by replacing literal '*' with the percent-encoded form '%2A'.
     */
    private function encodeValue(string $value): string
    {
        return str_replace('*', '%2A', $value);
    }

    /**
     * Computes the CRC32 checksum of the given string and returns it as an
     * 8-character uppercase hexadecimal string (unsigned 32-bit representation).
     */
    private function calculateCrc32(string $input): string
    {
        return sprintf('%08X', crc32($input) & 0xFFFFFFFF);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function validate(): void
    {
        $this->validateAcc($this->acc);

        if ($this->altAcc !== null) {
            $this->validateAltAcc($this->altAcc);
        }

        if ($this->am !== null) {
            $this->validateAm($this->am);
        }

        if ($this->cc !== null) {
            $this->validateCc($this->cc);
        }

        if ($this->dl !== null) {
            $this->validateDate($this->dl, 'DL');
        }

        if ($this->dt !== null) {
            $this->validateDate($this->dt, 'DT');
        }

        if ($this->msg !== null) {
            $this->validateMaxLength($this->msg, 60, 'MSG');
        }

        if ($this->nta !== null && $this->nt === null) {
            throw new ValidationException('NTA requires NT to be set.');
        }

        if ($this->nta !== null && $this->nt !== null) {
            $this->validateNta($this->nta, $this->nt);
        }

        if ($this->pt !== null) {
            $this->validateMaxLength($this->pt, 3, 'PT');
        }

        if ($this->rf !== null) {
            $this->validateRf($this->rf);
        }

        if ($this->rn !== null) {
            $this->validateMaxLength($this->rn, 35, 'RN');
        }

        if ($this->xId !== null) {
            $this->validateMaxLength($this->xId, 20, 'X-ID');
        }

        if ($this->xKs !== null) {
            $this->validateNumericString($this->xKs, 10, 'X-KS');
        }

        if ($this->xPer !== null) {
            if ($this->xPer < 1 || $this->xPer > 30) {
                throw new ValidationException('X-PER must be an integer between 1 and 30.');
            }
        }

        if ($this->xSelf !== null) {
            $this->validateMaxLength($this->xSelf, 60, 'X-SELF');
        }

        if ($this->xSs !== null) {
            $this->validateNumericString($this->xSs, 10, 'X-SS');
        }

        if ($this->xUrl !== null) {
            $this->validateMaxLength($this->xUrl, 140, 'X-URL');
        }

        if ($this->xVs !== null) {
            $this->validateNumericString($this->xVs, 10, 'X-VS');
        }
    }

    /**
     * Validates the ACC field: IBAN with an optional '+'-separated BIC.
     */
    private function validateAcc(string $acc): void
    {
        if (strlen($acc) > 46) {
            throw new ValidationException(
                sprintf('ACC must not exceed 46 characters, got %d.', strlen($acc))
            );
        }

        $parts = explode('+', $acc, 2);
        $iban  = $parts[0];
        $bic   = $parts[1] ?? null;

        if (!$this->isValidIban($iban)) {
            throw new ValidationException(
                sprintf('ACC contains an invalid IBAN: "%s".', $iban)
            );
        }

        if ($bic !== null && !$this->isValidBic($bic)) {
            throw new ValidationException(
                sprintf('ACC contains an invalid BIC/SWIFT code: "%s".', $bic)
            );
        }
    }

    /**
     * Validates the ALT-ACC field: comma-separated list of IBAN[+BIC] values.
     */
    private function validateAltAcc(string $altAcc): void
    {
        if (strlen($altAcc) > 93) {
            throw new ValidationException(
                sprintf('ALT-ACC must not exceed 93 characters, got %d.', strlen($altAcc))
            );
        }

        foreach (explode(',', $altAcc) as $account) {
            $this->validateAcc($account);
        }
    }

    /**
     * Validates the AM (amount) field.
     *
     * Rules:
     *  - Non-negative decimal, at most 2 decimal places.
     *  - Maximum value: 9 999 999.99.
     *  - String representation at most 10 characters.
     */
    private function validateAm(string $am): void
    {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $am)) {
            throw new ValidationException(
                'AM must be a non-negative decimal number with at most 2 decimal places (e.g. "555.55").'
            );
        }

        if ((float) $am > 9_999_999.99) {
            throw new ValidationException('AM must not exceed 9999999.99.');
        }

        if (strlen($am) > 10) {
            throw new ValidationException(
                sprintf('AM must not exceed 10 characters, got %d.', strlen($am))
            );
        }
    }

    /**
     * Validates the CC (currency) field. Only 'CZK' is currently permitted.
     */
    private function validateCc(string $cc): void
    {
        if ($cc !== 'CZK') {
            throw new ValidationException(
                sprintf('CC must be "CZK" (only CZK is currently permitted), got "%s".', $cc)
            );
        }
    }

    /**
     * Validates a date field in YYYYMMDD format.
     */
    private function validateDate(string $date, string $field): void
    {
        if (!preg_match('/^\d{8}$/', $date)) {
            throw new ValidationException(
                sprintf('%s must be a date in YYYYMMDD format, got "%s".', $field, $date)
            );
        }

        $dt = DateTime::createFromFormat('Ymd', $date);

        if ($dt === false || $dt->format('Ymd') !== $date) {
            throw new ValidationException(
                sprintf('%s is not a valid calendar date: "%s".', $field, $date)
            );
        }
    }

    /**
     * Validates the NTA (notification address) field against the chosen notification type.
     */
    private function validateNta(string $nta, NotificationType $nt): void
    {
        if ($nt === NotificationType::SMS) {
            if (!preg_match('/^\+?[0-9]+$/', $nta)) {
                throw new ValidationException(
                    'NTA for SMS notifications must contain only digits and an optional leading "+" sign.'
                );
            }

            if (strlen($nta) > 320) {
                throw new ValidationException(
                    sprintf('NTA for phone numbers must not exceed 320 characters, got %d.', strlen($nta))
                );
            }
        } else {
            if (filter_var($nta, FILTER_VALIDATE_EMAIL) === false) {
                throw new ValidationException(
                    sprintf('NTA for email notifications must be a valid e-mail address, got "%s".', $nta)
                );
            }

            if (strlen($nta) > 319) {
                throw new ValidationException(
                    sprintf('NTA for e-mail addresses must not exceed 319 characters, got %d.', strlen($nta))
                );
            }
        }
    }

    /**
     * Validates the RF (payment reference) field: digits only, max 16 characters.
     */
    private function validateRf(string $rf): void
    {
        if (!preg_match('/^\d+$/', $rf)) {
            throw new ValidationException('RF must contain digits only.');
        }

        if (strlen($rf) > 16) {
            throw new ValidationException(
                sprintf('RF must not exceed 16 digits, got %d.', strlen($rf))
            );
        }
    }

    /**
     * Validates a numeric string field (digits only, up to $maxLength characters).
     */
    private function validateNumericString(string $value, int $maxLength, string $field): void
    {
        if (!preg_match('/^\d+$/', $value)) {
            throw new ValidationException(
                sprintf('%s must contain digits only, got "%s".', $field, $value)
            );
        }

        if (strlen($value) > $maxLength) {
            throw new ValidationException(
                sprintf('%s must not exceed %d digits, got %d.', $field, $maxLength, strlen($value))
            );
        }
    }

    /**
     * Validates that a string does not exceed the allowed maximum byte length.
     */
    private function validateMaxLength(string $value, int $maxLength, string $field): void
    {
        if (strlen($value) > $maxLength) {
            throw new ValidationException(
                sprintf('%s must not exceed %d characters, got %d.', $field, $maxLength, strlen($value))
            );
        }
    }

    // -------------------------------------------------------------------------
    // IBAN / BIC helpers
    // -------------------------------------------------------------------------

    /**
     * Validates an IBAN using format check and modulo-97 checksum (ISO 13616).
     */
    private function isValidIban(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        // 2-letter country code + 2 check digits + 1–30 alphanumeric BBAN = 5–34 chars
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }

        // Rearrange: move first 4 characters to the end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Replace each letter with its numeric equivalent (A=10 … Z=35)
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // Compute modulo-97 digit by digit to avoid integer overflow
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }

    /**
     * Validates a BIC/SWIFT code (8 or 11 uppercase alphanumeric characters).
     *
     * Format: 4 alpha (institution) + 2 alpha (country) + 2 alphanumeric (location)
     *         + optional 3 alphanumeric (branch).
     */
    private function isValidBic(string $bic): bool
    {
        return (bool) preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper($bic));
    }
}
