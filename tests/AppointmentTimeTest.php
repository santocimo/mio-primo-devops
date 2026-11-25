<?php

use App\Appointment\AppointmentTime;
use PHPUnit\Framework\TestCase;

class AppointmentTimeTest extends TestCase
{
    public function testNormalizesTimestampAndPreservesDeviceTimezone(): void
    {
        self::assertSame(
            [
                'scheduled_at' => '2026-06-01 09:00:00',
                'scheduled_timezone' => 'Europe/Lisbon',
            ],
            AppointmentTime::normalizeInput('2026-06-01T10:00:00+01:00', 'Europe/Lisbon')
        );
    }

    public function testFormatsStoredUtcTimestampAsIso8601(): void
    {
        self::assertSame(
            '2026-06-01T09:00:00Z',
            AppointmentTime::toApiValue('2026-06-01 09:00:00', 'Europe/Lisbon')
        );
    }

    public function testKeepsLegacyLocalDateTimeUnchanged(): void
    {
        self::assertSame(
            [
                'scheduled_at' => '2026-06-01 10:00:00',
                'scheduled_timezone' => null,
            ],
            AppointmentTime::normalizeInput('2026-06-01T10:00', null)
        );
    }

    public function testRejectsInvalidTimestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AppointmentTime::normalizeInput('2026-02-30T10:00:00.000Z', 'Europe/Lisbon');
    }

    public function testRejectsUnknownTimezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AppointmentTime::normalizeInput('2026-06-01T10:00:00.000Z', 'Not/A_Timezone');
    }
}
