<?php

namespace Tests\Unit;

use App\Support\Pii;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PiiTest extends TestCase
{
    public function test_it_keeps_the_birth_code_and_century_digit_of_a_resident_number(): void
    {
        $this->assertSame('901231-1******', Pii::registration('901231-1234567'));
        $this->assertSame('901231-1******', Pii::registration('9012311234567'));
        $this->assertSame('020304-3******', Pii::registration('020304-3456789'));
    }

    public function test_anything_that_is_not_a_13_digit_rrn_keeps_only_three_digits(): void
    {
        // A 10-digit corporate business number is not an RRN. Splitting it at six
        // would disclose seven of its ten digits, so it takes the strict path.
        $this->assertSame('123*******', Pii::registration('123-45-67890'));
        $this->assertSame('123**', Pii::registration('12345'));
        $this->assertSame('901***********', Pii::registration('901231-12345678'));
    }

    public function test_it_returns_null_for_missing_or_non_numeric_registrations(): void
    {
        $this->assertNull(Pii::registration(null));
        $this->assertNull(Pii::registration(''));
        $this->assertNull(Pii::registration('   '));
        $this->assertNull(Pii::registration('없음'));
    }

    public function test_it_cuts_an_address_back_to_its_administrative_prefix(): void
    {
        $this->assertSame(
            '서울특별시 강남구',
            Pii::address('서울특별시 강남구 테헤란로 152 강남파이낸스센터 20층'),
        );
        $this->assertSame('경기도 성남시', Pii::address('  경기도   성남시  분당구 판교역로 235  '));
    }

    public function test_a_short_address_survives_and_a_blank_one_becomes_null(): void
    {
        $this->assertSame('부산광역시', Pii::address('부산광역시'));
        $this->assertNull(Pii::address(null));
        $this->assertNull(Pii::address('  '));
    }

    public function test_it_reduces_a_date_of_birth_to_its_year(): void
    {
        $this->assertSame(1990, Pii::birthYear(Carbon::parse('1990-12-31')));
        $this->assertNull(Pii::birthYear(null));
    }

    public function test_it_reports_contact_presence_without_disclosing_it(): void
    {
        $this->assertTrue(Pii::hasContact('010-1234-5678'));
        $this->assertTrue(Pii::hasContact(null, '010-1234-5678'));
        $this->assertFalse(Pii::hasContact(null, null));
        $this->assertFalse(Pii::hasContact('', '   '));
    }
}
