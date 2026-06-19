<?php

namespace Tests\Feature\Shareholders;

use App\Enums\PersonType;
use App\Support\KoreanRegistration;
use Tests\TestCase;

class KoreanRegistrationTest extends TestCase
{
    public function test_an_individual_rrn_yields_identity_and_birth_date(): void
    {
        $reg = KoreanRegistration::from('900101-1234567');

        $this->assertSame('9001011234567', $reg->digits);
        $this->assertSame(PersonType::Individual, $reg->personType());
        $this->assertSame('900101', $reg->dateOfBirthCode());
        $this->assertSame('1234567', $reg->code());
        $this->assertSame('1990-01-01', $reg->dateOfBirth()->toDateString());
    }

    public function test_the_century_digit_selects_the_2000s(): void
    {
        // Suffix begins with 3 → born in the 2000s.
        $reg = KoreanRegistration::from('0503053000000');

        $this->assertSame('2005-03-05', $reg->dateOfBirth()->toDateString());
    }

    public function test_a_short_number_is_treated_as_a_corporation(): void
    {
        $reg = KoreanRegistration::from('12345');

        $this->assertSame(PersonType::Corporation, $reg->personType());
        $this->assertSame('12345', $reg->code());
        $this->assertSame('12345', $reg->dateOfBirthCode());
        $this->assertNull($reg->dateOfBirth());
    }

    public function test_an_impossible_date_returns_null(): void
    {
        // 991340 — month 13, day 40.
        $this->assertNull(KoreanRegistration::from('9913401234567')->dateOfBirth());
    }
}
