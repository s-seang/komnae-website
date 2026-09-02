<?php

namespace Tests\Unit\Khmer;

use App\Support\Khmer\KhmerNormalizer;
use Tests\TestCase;

class KhmerNormalizerTest extends TestCase
{
    private KhmerNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new KhmerNormalizer;
    }

    public function test_it_reorders_a_vowel_written_before_its_coeng()
    {
        $this->assertSame('ខ្មែរ', $this->normalizer->normalize('ខែ្មរ'));
    }

    public function test_it_leaves_already_normal_text_untouched()
    {
        $text = 'ភាសាខ្មែរជាភាសាមួយដ៏ស្រស់ស្អាត';

        $this->assertSame($text, $this->normalizer->normalize($text));
    }

    public function test_it_keeps_the_zero_width_space_that_separates_words()
    {
        $normalized = $this->normalizer->normalize("ខ្ញុំ\u{200B}ទៅ\u{200B}សាលា");

        $this->assertSame("ខ្ញុំ\u{200B}ទៅ\u{200B}សាលា", $normalized);
        $this->assertSame(2, mb_substr_count($normalized, "\u{200B}"));
    }

    public function test_it_does_not_drop_subscript_consonants()
    {
        $normalized = $this->normalizer->normalize('ស្រុកខ្មែរ');

        $this->assertSame(2, mb_substr_count($normalized, "\u{17D2}"));
    }
}
