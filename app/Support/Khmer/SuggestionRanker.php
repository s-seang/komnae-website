<?php

namespace App\Support\Khmer;

/**
 * Ranked correction candidates for a suspected misspelling.
 *
 * Candidates come from two places: the hand-curated Khmer confusion pairs that
 * ship with the dictionary (ក↔គ, ិ↔ី, …), which cover the mistakes people
 * actually make, and a plain edit-distance scan for everything else.
 *
 * Suggestions are proposals. Nothing here rewrites the user's text.
 */
class SuggestionRanker
{
    private const MAX_DISTANCE = 2;

    /** Confusion-pair edits are likelier than arbitrary ones, so they cost less. */
    private const CONFUSION_DISTANCE = 0.5;

    /** How much one edit is worth in -log probability when ranking. */
    private const EDIT_PENALTY = 3.0;

    /**
     * Reward for a candidate built from the same consonants, differing only in
     * vowels and diacritics — by far the most common Khmer typing mistake.
     */
    private const SAME_CONSONANTS_BONUS = 3.0;

    public function __construct(
        private readonly Dictionary $dictionary,
        private readonly KhmerSegmenter $segmenter,
    ) {}

    /**
     * @return list<array{word: string, distance: float, score: float}>
     */
    public function suggest(string $word, int $limit = 5): array
    {
        $candidates = $this->dictionary->nearest($word, self::MAX_DISTANCE);

        foreach ($this->confusionVariants($word) as $variant) {
            $candidates[$variant] = min($candidates[$variant] ?? PHP_INT_MAX, self::CONFUSION_DISTANCE);
        }

        unset($candidates[$word]);

        $suggestions = [];
        $consonants = $this->consonants($word);

        foreach ($candidates as $candidate => $distance) {
            $score = $this->segmenter->cost((string) $candidate) + ($distance * self::EDIT_PENALTY);

            if ($this->consonants((string) $candidate) === $consonants) {
                $score -= self::SAME_CONSONANTS_BONUS;
            }

            $suggestions[] = [
                'word' => (string) $candidate,
                'distance' => (float) $distance,
                'score' => $score,
            ];
        }

        usort($suggestions, fn (array $a, array $b) => $a['score'] <=> $b['score']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * The word with every vowel, diacritic and coeng mark removed.
     */
    private function consonants(string $word): string
    {
        return preg_replace('/[\x{17B4}-\x{17D3}]/u', '', $word) ?? $word;
    }

    /**
     * Dictionary words reachable by applying one confusion pair to the input.
     *
     * @return list<string>
     */
    private function confusionVariants(string $word): array
    {
        $variants = [];

        foreach ($this->dictionary->confusionPairs() as [$from, $to]) {
            if (! str_contains($word, $from)) {
                continue;
            }

            $offset = 0;

            while (($position = mb_strpos($word, $from, $offset)) !== false) {
                $variant = mb_substr($word, 0, $position).$to.mb_substr($word, $position + mb_strlen($from));

                if ($this->dictionary->has($variant)) {
                    $variants[] = $variant;
                }

                $offset = $position + 1;
            }
        }

        return $variants;
    }
}
