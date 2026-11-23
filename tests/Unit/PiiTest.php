<?php

namespace Tests\Unit;

use App\Support\Pii;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function contactsInNotes(): array
    {
        return [
            'mobile, hyphenated' => ['010-1234-5678', '[전화번호]'],
            'mobile, bare' => ['01012345678', '[전화번호]'],
            'mobile, spaced' => ['010 1234 5678', '[전화번호]'],
            'mobile, dotted' => ['010.1234.5678', '[전화번호]'],
            'mobile, international' => ['+82 10-1234-5678', '[전화번호]'],
            'mobile, international bare' => ['+821012345678', '[전화번호]'],
            'Seoul landline' => ['02-123-4567', '[전화번호]'],
            'Seoul landline, bracketed' => ['(02) 123-4567', '[전화번호]'],
            'regional landline' => ['031-1234-5678', '[전화번호]'],
            'internet phone' => ['070-1234-5678', '[전화번호]'],
            'national number' => ['1588-1234', '[전화번호]'],
            'local number without area code' => ['사무실 555-1234', '사무실 [전화번호]'],
            'resident number' => ['901231-1234567', '[실명번호]'],
            'resident number, bare' => ['9012311234567', '[실명번호]'],
            'resident number, half masked' => ['본인 901231-1****** 확인', '본인 [실명번호] 확인'],
            'email' => ['a.b@example.co.kr 로 메일', '[이메일] 로 메일'],
            'inside Korean text' => ['연락처01012345678로 연락', '연락처[전화번호]로 연락'],
            'account number' => ['신한 110-123-456789', '신한 [전화번호]'],
        ];
    }

    #[DataProvider('contactsInNotes')]
    public function test_it_redacts_contact_details_from_a_note(string $note, string $expected): void
    {
        $this->assertSame($expected, Pii::note($note));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function harmlessNotes(): array
    {
        return [
            'share count' => ['2,720,000주 위임'],
            'amount' => ['15,000,000원'],
            'day and count' => ['9월 15일 3회 방문'],
            'dotted date then a count' => ['2026.09.15 3회 방문'],
            'iso date' => ['2026-09-15 재방문'],
            'year range' => ['2025-2026 시즌'],
        ];
    }

    #[DataProvider('harmlessNotes')]
    public function test_it_leaves_ordinary_numbers_in_a_note_alone(string $note): void
    {
        $this->assertSame($note, Pii::note($note));
    }

    public function test_a_blank_note_becomes_null(): void
    {
        $this->assertNull(Pii::note(null));
        $this->assertNull(Pii::note(''));
        $this->assertNull(Pii::note("  \n "));
        $this->assertSame('부재중', Pii::note('  부재중  '));
    }
}
