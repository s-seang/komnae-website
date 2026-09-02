<?php

namespace Tests\Unit\Khmer;

use App\Support\Khmer\KhmerSegmenter;
use Tests\TestCase;

class KhmerSegmenterTest extends TestCase
{
    public function test_it_splits_unspaced_khmer_into_words()
    {
        $segments = $this->segmenter()->segment('ខ្ញុំចូលចិត្តរៀនភាសាខ្មែរណាស់');

        $this->assertSame(
            ['ខ្ញុំ', 'ចូលចិត្ត', 'រៀន', 'ភាសា', 'ខ្មែរ', 'ណាស់'],
            array_column($segments, 'word'),
        );
    }

    public function test_offsets_are_character_positions_not_bytes()
    {
        $text = 'ខ្ញុំចូលចិត្តរៀនភាសាខ្មែរណាស់';

        foreach ($this->segmenter()->segment($text) as $segment) {
            $this->assertSame(
                $segment['word'],
                mb_substr($text, $segment['start'], $segment['length']),
                'Segment offsets must address characters so the frontend can highlight the right span.',
            );
        }
    }

    public function test_it_marks_characters_that_start_no_dictionary_word_as_unknown()
    {
        $segments = $this->segmenter()->segment('ភាសាXខ្មែរ');

        $unknown = array_values(array_filter($segments, fn (array $segment) => ! $segment['known']));

        $this->assertSame(['X'], array_column($unknown, 'word'));
    }

    public function test_a_common_word_costs_less_than_a_rare_one()
    {
        $segmenter = $this->segmenter();

        $this->assertLessThan($segmenter->cost('លោ'), $segmenter->cost('លោក'));
    }

    private function segmenter(): KhmerSegmenter
    {
        return app(KhmerSegmenter::class);
    }
}
