<?php

declare(strict_types=1);

namespace Rajtik76\PaymentQrCodePhp\Tests;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Rajtik76\PaymentQrCodePhp\Enum\Frequency;
use Rajtik76\PaymentQrCodePhp\Enum\HeaderType;
use Rajtik76\PaymentQrCodePhp\Enum\NotificationType;
use Rajtik76\PaymentQrCodePhp\Exception\ValidationException;
use Rajtik76\PaymentQrCodePhp\SpdQrCode;

class SpdQrCodeTest extends TestCase
{
    // =========================================================================
    // generate() – valid output
    // =========================================================================

    public function testGenerateMinimalOnlyAccCastClassAsString(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null);

        self::assertSame('SPD*1.0*ACC:CZ3301000000000002970297*', (string)$qr);
    }

    public function testGenerateMinimalOnlyAcc(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null);

        self::assertSame('SPD*1.0*ACC:CZ3301000000000002970297*', $qr->generate());
    }

    public function testGenerateDefaultCurrencyIncluded(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297');

        self::assertSame('SPD*1.0*ACC:CZ3301000000000002970297*CC:CZK*', $qr->generate());
    }

    public function testGenerateWithAmount(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            am: '555.55',
        );

        self::assertSame('SPD*1.0*ACC:CZ3301000000000002970297*AM:555.55*CC:CZK*', $qr->generate());
    }

    public function testGenerateWithAccAndBic(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297+GIBACZPX',
            cc: null,
        );

        self::assertSame('SPD*1.0*ACC:CZ3301000000000002970297+GIBACZPX*', $qr->generate());
    }

    public function testGenerateScdHeader(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            headerType: HeaderType::SCD,
            cc: null,
        );

        self::assertSame('SCD*1.0*ACC:CZ3301000000000002970297*', $qr->generate());
    }

    public function testGenerateInstantPayment(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            cc: null,
            pt: 'IP',
        );

        self::assertSame('SPD*1.0*ACC:CZ3301000000000002970297*PT:IP*', $qr->generate());
    }

    public function testGenerateStandingOrder(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            am: '555.55',
            dh: false,
            dl: '20230430',
            dt: '20210430',
            frq: Frequency::Monthly,
            msg: 'PRAVIDELNY PRISPEVEK NA NADACI',
        );

        self::assertSame(
            'SPD*1.0*ACC:CZ3301000000000002970297*AM:555.55*CC:CZK*DH:0*DL:20230430*DT:20210430*FRQ:1M*MSG:PRAVIDELNY PRISPEVEK NA NADACI*',
            $qr->generate()
        );
    }

    public function testGenerateStandingOrderDhTrue(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            cc: null,
            dh: true,
            frq: Frequency::Annually,
        );

        self::assertSame(
            'SPD*1.0*ACC:CZ3301000000000002970297*DH:1*FRQ:1Y*',
            $qr->generate()
        );
    }

    public function testGenerateWithAllFrequencies(): void
    {
        $cases = [
            [Frequency::Daily,        '1D'],
            [Frequency::Monthly,      '1M'],
            [Frequency::Quarterly,    '3M'],
            [Frequency::SemiAnnually, '6M'],
            [Frequency::Annually,     '1Y'],
        ];

        foreach ($cases as [$freq, $value]) {
            $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null, frq: $freq);
            self::assertStringContainsString('FRQ:' . $value . '*', $qr->generate());
        }
    }

    public function testGenerateWithSmsNotification(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            cc: null,
            nt: NotificationType::SMS,
            nta: '+420123456789',
        );

        self::assertSame(
            'SPD*1.0*ACC:CZ3301000000000002970297*NT:P*NTA:+420123456789*',
            $qr->generate()
        );
    }

    public function testGenerateWithEmailNotification(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            cc: null,
            nt: NotificationType::Email,
            nta: 'frantisek.koudelka@abc.cz',
        );

        self::assertSame(
            'SPD*1.0*ACC:CZ3301000000000002970297*NT:E*NTA:frantisek.koudelka@abc.cz*',
            $qr->generate()
        );
    }

    public function testGenerateWithCzechPaymentSymbols(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            dt: '20210430',
            msg: 'PRISPEVEK NA NADACI',
            rf: '7004139146',
            xKs: '0558',
            xSs: '1234567890',
            xVs: '0987654321',
        );

        self::assertSame(
            'SPD*1.0*ACC:CZ3301000000000002970297*CC:CZK*DT:20210430*MSG:PRISPEVEK NA NADACI*RF:7004139146*X-KS:0558*X-SS:1234567890*X-VS:0987654321*',
            $qr->generate()
        );
    }

    public function testGenerateWithAllExtendedFields(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            cc: null,
            xId: 'PAYMENT-ID-001',
            xKs: '0308',
            xPer: 7,
            xSelf: 'MOJE PLATBA',
            xSs: '1234567890',
            xUrl: 'HTTP://WWW.EXAMPLE.COM/',
            xVs: '0987654321',
        );

        $output = $qr->generate();

        self::assertStringContainsString('X-ID:PAYMENT-ID-001*', $output);
        self::assertStringContainsString('X-KS:0308*', $output);
        self::assertStringContainsString('X-PER:7*', $output);
        self::assertStringContainsString('X-SELF:MOJE PLATBA*', $output);
        self::assertStringContainsString('X-SS:1234567890*', $output);
        self::assertStringContainsString('X-URL:HTTP://WWW.EXAMPLE.COM/*', $output);
        self::assertStringContainsString('X-VS:0987654321*', $output);
    }

    public function testGenerateWithAltAcc(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            altAcc: 'CZ5855000000001265098001',
            cc: null,
        );

        self::assertSame(
            'SPD*1.0*ACC:CZ3301000000000002970297*ALT-ACC:CZ5855000000001265098001*',
            $qr->generate()
        );
    }

    public function testGenerateFieldsAreAlphabeticallyOrdered(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            am: '100.00',
            dt: '20250101',
            msg: 'TEST',
            rf: '123',
            xVs: '456',
        );

        $output = $qr->generate();
        // Use '*KEY:' pattern to avoid false matches (e.g. 'CC:' inside 'ACC:')
        $accPos  = strpos($output, '*ACC:');
        $amPos   = strpos($output, '*AM:');
        $ccPos   = strpos($output, '*CC:');
        $dtPos   = strpos($output, '*DT:');
        $msgPos  = strpos($output, '*MSG:');
        $rfPos   = strpos($output, '*RF:');
        $xvsPos  = strpos($output, '*X-VS:');

        self::assertLessThan($amPos, $accPos);
        self::assertLessThan($ccPos, $amPos);
        self::assertLessThan($dtPos, $ccPos);
        self::assertLessThan($msgPos, $dtPos);
        self::assertLessThan($rfPos, $msgPos);
        self::assertLessThan($xvsPos, $rfPos);
    }

    // =========================================================================
    // CRC32
    // =========================================================================

    public function testGenerateWithCrc32AppendsCrc32Field(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            withCrc32: true,
        );

        $output = $qr->generate();

        self::assertStringEndsWith('*', $output);
        self::assertMatchesRegularExpression('/\*CRC32:[0-9A-F]{8}\*$/', $output);
    }

    public function testGenerateWithCrc32CorrectChecksum(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            withCrc32: true,
        );

        $output   = $qr->generate();
        $baseStr  = 'SPD*1.0*ACC:CZ3301000000000002970297*CC:CZK*';
        $expected = sprintf('%08X', crc32($baseStr) & 0xFFFFFFFF);

        self::assertStringContainsString('CRC32:' . $expected . '*', $output);
    }

    public function testGenerateWithoutCrc32DoesNotContainCrc32Field(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297');

        self::assertStringNotContainsString('CRC32:', $qr->generate());
    }

    // =========================================================================
    // Special character encoding
    // =========================================================================

    public function testAsteriskInMsgIsPercentEncoded(): void
    {
        $qr = new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            cc: null,
            msg: 'PAY*NOW',
        );

        self::assertStringContainsString('MSG:PAY%2ANOW*', $qr->generate());
    }

    // =========================================================================
    // Validation – invalid inputs
    // =========================================================================

    public function testInvalidIbanThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/IBAN/i');

        new SpdQrCode(acc: 'NOTANIBAN');
    }

    public function testIbanWithWrongChecksumThrows(): void
    {
        $this->expectException(ValidationException::class);

        // Valid format but wrong check digits (CZ00 instead of the correct pair)
        new SpdQrCode(acc: 'CZ0001000000000002970297');
    }

    public function testInvalidBicThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/BIC/i');

        new SpdQrCode(acc: 'CZ3301000000000002970297+INVALID!');
    }

    public function testAccTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        // 47-character string that is not a valid IBAN anyway, but should fail length first
        new SpdQrCode(acc: str_repeat('A', 47));
    }

    public function testAmountWithMoreThanTwoDecimalPlacesThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', am: '100.123');
    }

    public function testAmountExceedsMaximumThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', am: '10000000.00');
    }

    public function testAmountNegativeThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', am: '-1.00');
    }

    public function testAmountNonNumericThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', am: 'abc');
    }

    public function testInvalidCurrencyThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/CZK/');

        new SpdQrCode(acc: 'CZ3301000000000002970297', cc: 'EUR');
    }

    public function testInvalidDateFormatThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', dt: '01-01-2025');
    }

    public function testInvalidDateValueThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', dt: '20250132'); // day 32
    }

    public function testMsgTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', msg: str_repeat('A', 61));
    }

    public function testRnTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', rn: str_repeat('A', 36));
    }

    public function testRfNonNumericThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', rf: '12AB56');
    }

    public function testRfTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', rf: '12345678901234567'); // 17 digits
    }

    public function testXPerBelowMinimumThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xPer: 0);
    }

    public function testXPerAboveMaximumThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xPer: 31);
    }

    public function testXVsNonNumericThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xVs: '123ABC');
    }

    public function testXVsTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xVs: '12345678901'); // 11 digits
    }

    public function testXSsNonNumericThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xSs: '12X4');
    }

    public function testXKsNonNumericThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xKs: '0X58');
    }

    public function testNtaWithoutNtThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/NT/');

        new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            nta: '+420123456789',
        );
    }

    public function testInvalidPhoneForSmsNotificationThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            nt: NotificationType::SMS,
            nta: 'not-a-phone',
        );
    }

    public function testInvalidEmailForEmailNotificationThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(
            acc: 'CZ3301000000000002970297',
            nt: NotificationType::Email,
            nta: 'not-an-email',
        );
    }

    public function testXUrlTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xUrl: str_repeat('A', 141));
    }

    public function testXIdTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xId: str_repeat('A', 21));
    }

    public function testXSelfTooLongThrows(): void
    {
        $this->expectException(ValidationException::class);

        new SpdQrCode(acc: 'CZ3301000000000002970297', xSelf: str_repeat('A', 61));
    }

    // =========================================================================
    // Boundary / valid edge cases
    // =========================================================================

    public function testAmountZeroIsValid(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', am: '0');
        self::assertStringContainsString('AM:0*', $qr->generate());
    }

    public function testAmountMaxValueIsValid(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', am: '9999999.99');
        self::assertStringContainsString('AM:9999999.99*', $qr->generate());
    }

    public function testAmountWithOneDecimalIsValid(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', am: '100.5');
        self::assertStringContainsString('AM:100.5*', $qr->generate());
    }

    public function testXPerBoundaryValues(): void
    {
        $qr1 = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null, xPer: 1);
        self::assertStringContainsString('X-PER:1*', $qr1->generate());

        $qr2 = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null, xPer: 30);
        self::assertStringContainsString('X-PER:30*', $qr2->generate());
    }

    public function testXKsWithLeadingZerosPreserved(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null, xKs: '0308');
        self::assertStringContainsString('X-KS:0308*', $qr->generate());
    }

    public function testRfMaxLengthIsValid(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null, rf: '1234567890123456');
        self::assertStringContainsString('RF:1234567890123456*', $qr->generate());
    }

    public function testDlValidDate(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297', cc: null, dl: '20251231');
        self::assertStringContainsString('DL:20251231*', $qr->generate());
    }

    /**
     * @param string $iban
     */
    #[DataProvider('validIbanProvider')]
    public function testValidIbansAreAccepted(string $iban): void
    {
        $qr = new SpdQrCode(acc: $iban, cc: null);
        self::assertStringContainsString('ACC:' . $iban . '*', $qr->generate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validIbanProvider(): array
    {
        return [
            'Czech IBAN 1'   => ['CZ3301000000000002970297'],
            'Czech IBAN 2'   => ['CZ5855000000001265098001'],
            'German IBAN'    => ['DE89370400440532013000'],
            'Austrian IBAN'  => ['AT611904300234573201'],
        ];
    }

    /**
     * @param string $iban
     */
    #[DataProvider('invalidIbanProvider')]
    public function testInvalidIbansAreRejected(string $iban): void
    {
        $this->expectException(ValidationException::class);
        new SpdQrCode(acc: $iban);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIbanProvider(): array
    {
        return [
            'empty string'          => [''],
            'too short'             => ['CZ33'],
            'bad country code'      => ['12DE89370400440532013000'],
            'wrong check digits'    => ['CZ0001000000000002970297'],
            'non-alphanumeric BBAN' => ['CZ33!!!!!!!!!!!!!!!!!!!!!!'],
        ];
    }

    // =========================================================================
    // generateBase64()
    // =========================================================================

    /**
     * Decodes the base64 payload from a PNG data URI produced by generateBase64().
     */
    private function decodePngDataUri(string $dataUri): string
    {
        self::assertStringStartsWith('data:image/png;base64,', $dataUri);

        return (string) base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64ReturnsDataUri(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297');

        self::assertStringStartsWith('data:image/png;base64,', $qr->generateBase64());
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64ContainsValidPngMagicBytes(): void
    {
        $qr     = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $binary = $this->decodePngDataUri($qr->generateBase64());

        // PNG signature: 8 fixed bytes – \x89 P N G \r \n \x1a \n
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $binary);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64DefaultParamsMatchManualConstruction(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297');

        // Build the expected data URI manually using the same endroid defaults
        $expected = (new PngWriter())
            ->write(new QrCode(data: $qr->generate()))
            ->getDataUri();

        self::assertSame($expected, $qr->generateBase64());
    }

    /**
     * With RoundBlockSizeMode::Margin (default), the library guarantees:
     *   outerSize = size + 2 × margin
     * so the image dimensions are fully deterministic regardless of QR content.
     */
    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64DefaultImageDimensions(): void
    {
        $qr     = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $image  = imagecreatefromstring($this->decodePngDataUri($qr->generateBase64()));

        self::assertInstanceOf(\GdImage::class, $image);
        // size=300, margin=10 → outerSize = 300 + 2×10 = 320
        self::assertSame(320, imagesx($image));
        self::assertSame(320, imagesy($image));
        imagedestroy($image);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64CustomSizeAffectsDimensions(): void
    {
        $qr    = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $image = imagecreatefromstring($this->decodePngDataUri($qr->generateBase64(size: 200)));

        self::assertInstanceOf(\GdImage::class, $image);
        // size=200, margin=10 → outerSize = 200 + 2×10 = 220
        self::assertSame(220, imagesx($image));
        self::assertSame(220, imagesy($image));
        imagedestroy($image);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64CustomMarginAffectsDimensions(): void
    {
        $qr    = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $image = imagecreatefromstring($this->decodePngDataUri($qr->generateBase64(margin: 5)));

        self::assertInstanceOf(\GdImage::class, $image);
        // size=300, margin=5 → outerSize = 300 + 2×5 = 310
        self::assertSame(310, imagesx($image));
        self::assertSame(310, imagesy($image));
        imagedestroy($image);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64SizesProduceDifferentImages(): void
    {
        $qr      = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $small   = $qr->generateBase64(size: 150);
        $large   = $qr->generateBase64(size: 300);

        self::assertNotSame($small, $large);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64ErrorCorrectionLevelsProduceDifferentImages(): void
    {
        $qr   = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $low  = $qr->generateBase64(errorCorrectionLevel: ErrorCorrectionLevel::Low);
        $high = $qr->generateBase64(errorCorrectionLevel: ErrorCorrectionLevel::High);

        self::assertNotSame($low, $high);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64CustomForegroundColorProducesDifferentImage(): void
    {
        $qr      = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $black   = $qr->generateBase64(foregroundColor: new Color(0, 0, 0));
        $red     = $qr->generateBase64(foregroundColor: new Color(255, 0, 0));

        self::assertNotSame($black, $red);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64CustomBackgroundColorProducesDifferentImage(): void
    {
        $qr      = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $white   = $qr->generateBase64(backgroundColor: new Color(255, 255, 255));
        $yellow  = $qr->generateBase64(backgroundColor: new Color(255, 255, 0));

        self::assertNotSame($white, $yellow);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64CustomEncodingProducesSameImage(): void
    {
        // UTF-8 is the only supported encoding for Czech payment codes; switching
        // to it explicitly must produce the same result as the default.
        $qr        = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $explicit  = $qr->generateBase64(encoding: new Encoding('UTF-8'));
        $default   = $qr->generateBase64();

        self::assertSame($default, $explicit);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64RoundBlockSizeModeEnlargeProducesDifferentImage(): void
    {
        $qr = new SpdQrCode(acc: 'CZ3301000000000002970297');

        // The SPAYD string uses only QR-alphanumeric characters → version 2 QR (25×25 modules).
        // 300 ÷ 25 = 12 exactly, so floor = ceil and Margin = Enlarge at size 300.
        // Using size 301 (not divisible by 25) forces a fractional block size (12.04):
        //   Margin  → floor(12.04)=12 → innerSize=300, outerSize=301+2×10=321
        //   Enlarge → ceil(12.04) =13 → innerSize=325, outerSize=325+2×10=345
        // The different outer dimensions guarantee different PNG binaries.
        $margin  = $qr->generateBase64(size: 301, roundBlockSizeMode: RoundBlockSizeMode::Margin);
        $enlarge = $qr->generateBase64(size: 301, roundBlockSizeMode: RoundBlockSizeMode::Enlarge);

        self::assertNotSame($margin, $enlarge);
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64UsesCurrentSpaydStringAsQrData(): void
    {
        // Verify that different SPAYD payloads produce different QR images
        $qr1 = new SpdQrCode(acc: 'CZ3301000000000002970297', am: '100.00');
        $qr2 = new SpdQrCode(acc: 'CZ3301000000000002970297', am: '200.00');

        self::assertNotSame($qr1->generateBase64(), $qr2->generateBase64());
    }

    #[RequiresPhpExtension('gd')]
    public function testGenerateBase64WithCrc32EmbedsDifferentPayload(): void
    {
        $qrPlain  = new SpdQrCode(acc: 'CZ3301000000000002970297');
        $qrCrc32  = new SpdQrCode(acc: 'CZ3301000000000002970297', withCrc32: true);

        // Different SPAYD strings (with vs without CRC32 field) → different QR images
        self::assertNotSame($qrPlain->generateBase64(), $qrCrc32->generateBase64());
    }
}
