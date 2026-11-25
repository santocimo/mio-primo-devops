<?php

namespace App\Appointment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class AppointmentTime
{
    /**
     * @return array{scheduled_at: string, scheduled_timezone: ?string}
     */
    public static function normalizeInput(string $value, ?string $timezone): array
    {
        $timezone = trim((string)$timezone);
        if ($timezone === '') {
            return [
                'scheduled_at' => self::normalizeLegacyValue($value),
                'scheduled_timezone' => null,
            ];
        }

        if (
            !in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL), true)
            || !preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/D',
                $value
            )
        ) {
            throw new InvalidArgumentException('A valid ISO timestamp and timezone are required.');
        }

        try {
            $instant = new DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('The appointment timestamp is invalid.', 0, $exception);
        }

        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new InvalidArgumentException('The appointment timestamp is invalid.');
        }

        return [
            'scheduled_at' => $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'scheduled_timezone' => $timezone,
        ];
    }

    public static function toApiValue(string $scheduledAt, ?string $timezone): string
    {
        if ($timezone === null || trim($timezone) === '') {
            return $scheduledAt;
        }

        $instant = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $scheduledAt,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $instant === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('The stored appointment timestamp is invalid.');
        }

        return $instant->format('Y-m-d\TH:i:s\Z');
    }

    private static function normalizeLegacyValue(string $value): string
    {
        $value = str_replace('T', ' ', trim($value));
        if (strlen($value) === 16) {
            $value .= ':00';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)) {
            throw new InvalidArgumentException('The appointment date and time are invalid.');
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('The appointment date and time are invalid.');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
