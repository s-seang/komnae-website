<?php

namespace Tests\Unit\Khmer;

use App\Support\Khmer\Dictionary;
use App\Support\Khmer\SpellChecker;
use Tests\TestCase;

class SpellCheckerTest extends TestCase
{
    public function test_it_passes_a_correctly_spelled_sentence()
    {
        $this->assertSame([], $this->checker()->check('សួស្តីពិភពលោក'));
    }

    public function test_it_catches_a_wrong_final_consonant()
    {
        $found = $this->checker()->check('សួស្តីពិភពលោគ');

        $this->assertCount(1, $found);
        $this->assertSame('លោគ', $found[0]['word']);
        $this->assertContains('លោក', $found[0]['suggestions']);
    }

    public function test_it_catches_a_dropped_diacritic()
    {
        $found = $this->checker()->check('ខ្ញុំចូលចិត្តរៀនភាសាខ្មែរណាស');

        $this->assertSame('ណាស', $found[0]['word']);
        $this->assertSame('ណាស់', $found[0]['suggestions'][0]);
    }

    public function test_the_reported_span_addresses_the_original_characters()
    {
        $text = 'ខ្ញុំចូលចិត្តរៀនភាសាខ្មែរណាស';

        foreach ($this->checker()->check($text) as $misspelling) {
            $this->assertSame(
                $misspelling['word'],
                mb_substr($text, $misspelling['start'], $misspelling['length']),
            );
        }
    }

    public function test_it_ignores_punctuation_digits_and_latin()
    {
        $this->assertSame([], $this->checker()->check('ភាសាខ្មែរ ១២៣ ។ Komnot 2026'));
    }

    /**
     * The false-positive guard: on real prose, anything flagged must at least be
     * a word the dictionary genuinely does not have. Flagging a correctly spelled
     * word is the failure this project cannot afford.
     */
    public function test_it_does_not_flag_real_words_in_a_real_paragraph()
    {
        $dictionary = app(Dictionary::class);
        $paragraph = file_get_contents(base_path('tests/Fixtures/khmer-paragraph.txt'));
        $found = $this->checker()->check($paragraph);

        $this->assertLessThanOrEqual(1, count($found), 'Too much noise on ordinary prose.');

        foreach ($found as $misspelling) {
            $this->assertFalse(
                $dictionary->has($misspelling['word']),
                "Flagged [{$misspelling['word']}], which is in the dictionary.",
            );
        }
    }

    private function checker(): SpellChecker
    {
        return app(SpellChecker::class);
    }
}
