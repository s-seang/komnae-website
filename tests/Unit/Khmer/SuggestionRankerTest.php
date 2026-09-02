<?php

namespace Tests\Unit\Khmer;

use App\Support\Khmer\SuggestionRanker;
use Tests\TestCase;

class SuggestionRankerTest extends TestCase
{
    public function test_it_ranks_the_intended_word_first()
    {
        $this->assertSame('លោក', $this->ranker()->suggest('លោគ')[0]['word']);
    }

    public function test_it_prefers_a_candidate_with_the_same_consonants()
    {
        $this->assertSame('ណាស់', $this->ranker()->suggest('ណាស')[0]['word']);
    }

    public function test_it_never_suggests_the_word_it_was_given()
    {
        $suggestions = array_column($this->ranker()->suggest('លោគ'), 'word');

        $this->assertNotContains('លោគ', $suggestions);
    }

    public function test_it_respects_the_limit()
    {
        $this->assertCount(3, $this->ranker()->suggest('លោគ', 3));
    }

    public function test_it_returns_nothing_for_a_word_with_no_close_neighbours()
    {
        $this->assertSame([], $this->ranker()->suggest('ឆ្គងឡប់ឡែងអាក្រក់ណាស់'));
    }

    private function ranker(): SuggestionRanker
    {
        return app(SuggestionRanker::class);
    }
}
