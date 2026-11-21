<?php

namespace Tests\Unit;

use App\Rules\EgyptianMobileNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Unit tests for the shared Egyptian-mobile rule.
 *
 * Valid: local 010/011/012/015 + 8 digits (11 digits), and the
 * international +20 / 20 forms. Spaces and dashes are ignored.
 */
class EgyptianMobileNumberTest extends TestCase
{
    /**
     * @dataProvider validNumbers
     */
    public function test_accepts_valid_egyptian_mobile_numbers(string $phone): void
    {
        $this->assertTrue(EgyptianMobileNumber::isValid($phone));
        $this->assertFalse(
            Validator::make(['phone' => $phone], ['phone' => new EgyptianMobileNumber])->fails()
        );
    }

    public static function validNumbers(): array
    {
        return [
            'Vodafone 010 (user example)' => ['01054125230'],
            'Etisalat 011 (user example)' => ['01115433208'],
            'Orange 012 (user example)' => ['01225874559'],
            'We 015' => ['01512345678'],
            'We 015, user example completed to 11 digits' => ['01559555555'],
            '+20 form' => ['+201012345678'],
            '20 form without +' => ['201012345678'],
            '+20 with spaces' => ['+20 101 234 5678'],
            'local with dashes' => ['010-1234-5678'],
            'local with spaces' => ['015 1234 5678'],
        ];
    }

    /**
     * @dataProvider invalidNumbers
     */
    public function test_rejects_invalid_numbers(string $phone): void
    {
        $this->assertFalse(EgyptianMobileNumber::isValid($phone));
        $this->assertTrue(
            Validator::make(['phone' => $phone], ['phone' => new EgyptianMobileNumber])->fails()
        );
    }

    public static function invalidNumbers(): array
    {
        return [
            'wrong prefix 013' => ['01312345678'],
            'wrong prefix 014' => ['01412345678'],
            'wrong prefix 016' => ['01612345678'],
            'wrong prefix 019' => ['01912345678'],
            '10 digits (one short)' => ['0101234567'],
            '12 digits (one too many)' => ['010123456789'],
            'letters' => ['0101234567a'],
            'letters only' => ['abcdef'],
            'Cairo landline 02' => ['0223456789'],
            'user 015 example as given (9 digits, 2 short)' => ['015595555'],
            'Saudi number' => ['+966555123456'],
        ];
    }

    public function test_rejects_empty_string_via_is_valid_only(): void
    {
        // An empty value fails isValid(), but Laravel skips
        // non-implicit rules for empty values — `required` is what
        // rejects an empty phone in the real rules.
        $this->assertFalse(EgyptianMobileNumber::isValid(''));
        $this->assertFalse(
            Validator::make(['phone' => ''], ['phone' => ['required', new EgyptianMobileNumber]])->passes()
        );
    }

    public function test_rejects_non_string_values(): void
    {
        $this->assertFalse(EgyptianMobileNumber::isValid(null));
        $this->assertFalse(EgyptianMobileNumber::isValid(['01012345678']));
    }

    public function test_failure_message_is_the_arabic_egyptian_mobile_message(): void
    {
        app()->setLocale('ar');

        $validator = Validator::make(
            ['phone' => '01312345678'],
            ['phone' => new EgyptianMobileNumber]
        );

        $this->assertSame(
            'رقم الهاتف يجب أن يكون رقمًا مصريًا صحيحًا يبدأ بـ 010 أو 011 أو 012 أو 015',
            $validator->errors()->first('phone')
        );
    }
}
