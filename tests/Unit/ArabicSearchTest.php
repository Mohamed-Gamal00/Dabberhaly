<?php

namespace Tests\Unit;

use App\Helper\ArabicSearch;
use PHPUnit\Framework\TestCase;

class ArabicSearchTest extends TestCase
{
    public function test_alef_variants_become_one_character_class(): void
    {
        $this->assertSame('[اأإآٱ]حمد', ArabicSearch::toRegExpPattern('احمد'));
        $this->assertSame('[اأإآٱ]حمد', ArabicSearch::toRegExpPattern('أحمد'));
    }

    public function test_teh_marbuta_heh_and_alef_maksura_yeh_become_classes(): void
    {
        $this->assertSame('ق[ةه]و[ةه]', ArabicSearch::toRegExpPattern('قهوة'));
        $this->assertSame('موس[ىي]', ArabicSearch::toRegExpPattern('موسى'));
    }

    public function test_diacritics_and_tatweel_are_stripped(): void
    {
        $this->assertSame('[اأإآٱ]حمد', ArabicSearch::toRegExpPattern('أَحْمَد'));
        $this->assertSame('سل[اأإآٱ]م', ArabicSearch::toRegExpPattern('سـلام'));
    }

    public function test_regex_metacharacters_from_user_input_are_escaped(): void
    {
        $this->assertSame('a\+b', ArabicSearch::toRegExpPattern('a+b'));
        $this->assertSame('a\.b', ArabicSearch::toRegExpPattern('a.b'));
    }

    public function test_empty_or_diacritics_only_terms_return_null(): void
    {
        $this->assertNull(ArabicSearch::toRegExpPattern(''));
        $this->assertNull(ArabicSearch::toRegExpPattern('   '));
        $this->assertNull(ArabicSearch::toRegExpPattern('ًٌ'));
    }
}
