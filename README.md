# rajtik76/payment-qr-code-php

[![Tests](https://github.com/rajtik76/payment-qr-code-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/rajtik76/payment-qr-code-php/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)

A PHP library for generating **SPAYD** (Short Payment Descriptor) QR codes used in Czech domestic payments. Compliant with the Czech Banking Association standard **v1.2** (effective 2022-01-01), covering payment orders (SPD), immediate payments, standing orders, and collection consents (SCD).

The library validates every input against the specification before producing output, so an invalid object simply cannot be constructed. The generated string is ready to be fed to any QR encoder. A built-in `generateBase64()` method renders a PNG directly via `endroid/qr-code` and returns it as a data URI.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.4 |
| ext-gd | any |
| endroid/qr-code | ^6.1 |

## Installation

```bash
composer require rajtik76/payment-qr-code-php
```

---

## Quick start

```php
use Rajtik76\QrCodePhp\SpdQrCode;

// Minimal – only the recipient account is required
$qr = new SpdQrCode(acc: 'CZ3301000000000002970297');

echo $qr->generate();
// SPD*1.0*ACC:CZ3301000000000002970297*CC:CZK*

// Cast to string produces the same result
echo (string) $qr;
```

```php
// Typical payment order
$qr = new SpdQrCode(
    acc: 'CZ3301000000000002970297',
    am:  '555.55',
    msg: 'PRISPEVEK NA NADACI',
    rf:  '7004139146',
    xVs: '0987654321',
    xSs: '1234567890',
    xKs: '0558',
    dt:  '20210430',
);

echo $qr->generate();
// SPD*1.0*ACC:CZ3301000000000002970297*AM:555.55*CC:CZK*DT:20210430*MSG:PRISPEVEK NA NADACI*RF:7004139146*X-KS:0558*X-SS:1234567890*X-VS:0987654321*
```

```php
// Render as a PNG data URI (suitable for an <img src="…"> tag)
$dataUri = $qr->generateBase64();
// data:image/png;base64,iVBORw0KGgo…
```

---

## Constructor parameters

All parameters are passed as **named arguments**. Only `$acc` is required; everything else defaults to `null` or a sensible value.

### Core fields

| Parameter | Type | SPAYD key | Description |
|---|---|---|---|
| `acc` | `string` | `ACC` | **Required.** Recipient IBAN, optionally followed by `+BIC`. E.g. `CZ3301000000000002970297` or `CZ3301000000000002970297+GIBACZPX`. Max 46 chars. IBAN checksum (mod-97) is validated. |
| `headerType` | `HeaderType` | header | `HeaderType::SPD` (payment order) or `HeaderType::SCD` (collection consent). Default `SPD`. |
| `altAcc` | `?string` | `ALT-ACC` | Alternative recipient accounts, comma-separated `IBAN[+BIC]` pairs. Max 93 chars. Each account is individually validated. |
| `am` | `?string` | `AM` | Amount. Non-negative decimal, at most 2 decimal places, range `0`–`9999999.99`. Pass as a string: `'555.55'`. |
| `cc` | `?string` | `CC` | ISO 4217 currency. Only `'CZK'` is currently permitted. Defaults to `'CZK'`; pass `null` to omit the field. |
| `dt` | `?string` | `DT` | Due date in `YYYYMMDD` format. For standing orders: first payment date. |
| `dl` | `?string` | `DL` | End/expiry date in `YYYYMMDD` format. For standing orders: expiry. For SCD: collection end. |
| `msg` | `?string` | `MSG` | Message for the recipient or standing order name. Max 60 chars. |
| `rf` | `?string` | `RF` | Payment reference number (digits only). Max 16 digits. |
| `rn` | `?string` | `RN` | Recipient name. Max 35 chars. |
| `pt` | `?string` | `PT` | Payment type. Pass `'IP'` to request an instant payment. Max 3 chars. |
| `frq` | `?Frequency` | `FRQ` | Recurring payment frequency. See [`Frequency`](#frequency) enum. |
| `dh` | `?bool` | `DH` | Death handling for standing orders: `false` = continue (`DH:0`), `true` = stop (`DH:1`), `null` = omit. |
| `nt` | `?NotificationType` | `NT` | Notification channel. See [`NotificationType`](#notificationtype) enum. |
| `nta` | `?string` | `NTA` | Notification address. Required when `nt` is set. Phone (`+digits`) for SMS, valid e-mail for Email. |
| `withCrc32` | `bool` | `CRC32` | When `true`, appends a CRC32 checksum field. Default `false`. |

### Extended Czech fields (`X-` prefix)

| Parameter | Type | SPAYD key | Description |
|---|---|---|---|
| `xVs` | `?string` | `X-VS` | Variable symbol. Digits only, max 10. |
| `xSs` | `?string` | `X-SS` | Specific symbol. Digits only, max 10. |
| `xKs` | `?string` | `X-KS` | Constant symbol. Digits only, max 10. Leading zeros are preserved. |
| `xPer` | `?int` | `X-PER` | Retry period in days for failed payments. Integer 1–30. |
| `xId` | `?string` | `X-ID` | Payer's internal payment identifier. Max 20 chars. |
| `xUrl` | `?string` | `X-URL` | Custom URL. Max 140 chars. |
| `xSelf` | `?string` | `X-SELF` | Message for the payer's own records. Max 60 chars. |

---

## Enums

### `HeaderType`

```php
use Rajtik76\QrCodePhp\Enum\HeaderType;

HeaderType::SPD  // Short Payment Descriptor  – payment orders, instant payments
HeaderType::SCD  // Short Collection Descriptor – collection consents / direct debits
```

### `Frequency`

```php
use Rajtik76\QrCodePhp\Enum\Frequency;

Frequency::Daily         // 1D
Frequency::Monthly       // 1M
Frequency::Quarterly     // 3M
Frequency::SemiAnnually  // 6M
Frequency::Annually      // 1Y
```

### `NotificationType`

```php
use Rajtik76\QrCodePhp\Enum\NotificationType;

NotificationType::SMS    // NT:P  – notify via SMS; NTA must be a phone number
NotificationType::Email  // NT:E  – notify via e-mail; NTA must be a valid address
```

---

## Generating a PNG image

`generateBase64()` wraps `generate()` with `endroid/qr-code`'s `PngWriter` and returns a `data:image/png;base64,…` URI. Every parameter mirrors the `QrCode` constructor exactly; all defaults are identical.

```php
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

$dataUri = $qr->generateBase64(
    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
    size:                 400,
    margin:               16,
    foregroundColor:      new Color(0, 0, 128),   // navy blue modules
    backgroundColor:      new Color(255, 255, 255),
);
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `encoding` | `EncodingInterface` | `UTF-8` | Character encoding. |
| `errorCorrectionLevel` | `ErrorCorrectionLevel` | `Low` | Error recovery capacity (Low / Medium / Quartile / High). |
| `size` | `int` | `300` | Target image dimension in pixels. |
| `margin` | `int` | `10` | Border around the QR code in pixels. |
| `roundBlockSizeMode` | `RoundBlockSizeMode` | `Margin` | How fractional module sizes are rounded. |
| `foregroundColor` | `ColorInterface` | black | QR module colour. |
| `backgroundColor` | `ColorInterface` | white | Background colour. |
| `logo` | `?LogoInterface` | `null` | Optional logo embedded in the centre. |
| `label` | `?LabelInterface` | `null` | Optional text label below the QR code. |

> **Note on image dimensions.** With the default `RoundBlockSizeMode::Margin`, the output image is exactly `size + 2 × margin` pixels on each side, regardless of QR content. At the defaults that is 320 × 320 px.

---

## Usage examples

### Standing order

```php
use Rajtik76\QrCodePhp\Enum\Frequency;
use Rajtik76\QrCodePhp\SpdQrCode;

$qr = new SpdQrCode(
    acc: 'CZ3301000000000002970297',
    am:  '555.55',
    frq: Frequency::Monthly,
    dt:  '20210430',
    dl:  '20230430',
    dh:  false,
    msg: 'PRAVIDELNY PRISPEVEK NA NADACI',
);

// SPD*1.0*ACC:CZ3301000000000002970297*AM:555.55*CC:CZK*DH:0*DL:20230430*DT:20210430*FRQ:1M*MSG:PRAVIDELNY PRISPEVEK NA NADACI*
```

### Instant payment (IP)

```php
$qr = new SpdQrCode(
    acc: 'CZ3301000000000002970297',
    am:  '199.00',
    pt:  'IP',
    msg: 'RYCHLA PLATBA',
);
```

### Collection consent (SCD)

```php
use Rajtik76\QrCodePhp\Enum\Frequency;
use Rajtik76\QrCodePhp\Enum\HeaderType;

$qr = new SpdQrCode(
    acc:        'CZ3301000000000002970297',
    headerType: HeaderType::SCD,
    am:         '555.55',
    frq:        Frequency::Monthly,
    dt:         '20210430',
    dl:         '20260430',
    dh:         false,
    msg:        'PRAVIDELNY PRISPEVEK NA NADACI',
);
```

### With CRC32 checksum

```php
$qr = new SpdQrCode(
    acc:       'CZ3301000000000002970297',
    am:        '100.00',
    withCrc32: true,
);

// SPD*1.0*ACC:CZ3301000000000002970297*AM:100.00*CC:CZK*CRC32:XXXXXXXX*
```

### Payment notification

```php
use Rajtik76\QrCodePhp\Enum\NotificationType;

$qr = new SpdQrCode(
    acc: 'CZ3301000000000002970297',
    am:  '250.00',
    nt:  NotificationType::Email,
    nta: 'platba@example.cz',
);
```

### Recipient account with BIC

```php
$qr = new SpdQrCode(
    acc: 'CZ3301000000000002970297+GIBACZPX',
    am:  '1000.00',
);
```

---

## Validation

Every field is validated in the constructor against the SPAYD v1.2 rules. A `Rajtik76\QrCodePhp\Exception\ValidationException` (extends `\InvalidArgumentException`) is thrown immediately on construction if any value is out of range or malformed. There is no deferred validation; a successfully constructed `SpdQrCode` object is always in a valid state.

```php
use Rajtik76\QrCodePhp\Exception\ValidationException;

try {
    $qr = new SpdQrCode(
        acc: 'CZ3301000000000002970297',
        am:  '10000000.00',  // exceeds maximum
    );
} catch (ValidationException $e) {
    echo $e->getMessage();
    // AM must not exceed 9999999.99.
}
```

---

## Output format

Fields are emitted in **alphabetical key order**, which is also the canonical order required by the CRC32 specification. The format is:

```
{HEADER}*{VERSION}*{KEY}:{VALUE}*{KEY}:{VALUE}*…
```

Literal `*` characters inside field values are automatically percent-encoded as `%2A`.

---

## Running the tests

```bash
composer install
./vendor/bin/phpunit
```

The test suite covers all 22 constructor parameters, all validation rules, CRC32 correctness, field ordering, special-character encoding, and image generation (PNG dimensions, magic bytes, per-parameter visual differences). Image tests require the GD extension and are automatically skipped when it is unavailable.

---

## Specification

[Czech Banking Association — *Standardy bankovních aktivit: Formát pro sdílení platebních údajů v rámci tuzemského platebního styku v ČZK prostřednictvím QR kódů*, version 1.2, effective 2022-01-01.](https://www.cbaonline.cz/journal_files_storage/standard-qr-kody-2021.pdf)

---

## License

MIT — see [LICENSE.md](LICENSE.md).
