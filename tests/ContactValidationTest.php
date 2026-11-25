<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/validation.php';

class ContactValidationTest extends TestCase
{
    public function testTaxCodeValidationChecksFormatAndControlCharacter(): void
    {
        self::assertTrue(validate_tax_code('RSSMRA85T10A562S'));
        self::assertFalse(validate_tax_code('RSSMRA85T10A562A'));
        self::assertFalse(validate_tax_code('invalid'));
    }

    public function testBirthDateValidationAcceptsRealDatesOnly(): void
    {
        self::assertTrue(validate_birth_date('2000-02-29'));
        self::assertFalse(validate_birth_date('1900-02-29'));
        self::assertFalse(validate_birth_date('01-02-2000'));
    }
}
