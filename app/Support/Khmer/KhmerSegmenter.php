<?php

namespace App\Support\Khmer;

/**
 * Khmer is written without spaces, so word boundaries come from the dictionary.
 *
 * This is a Viterbi search for the cheapest segmentation, where a word costs
 * -log(its corpus probability). Frequencies matter: without them the search
 * happily splits a common word into two rarer ones of the same total length.
 *
 * Offsets are character positions, never bytes.
 */
class KhmerSegmenter
{
    /**
     * Cost of consuming one character that starts no dictionary word. Must sit
     * above any real word's cost so unknown characters are the last resort.
     */
    private const UNKNOWN_COST = 30.0;

    public function __construct(private readonly Dictionary $dictionary) {}

    /**
     * @return list<array{word: string, start: int, length: int, known: bool, cost: float}>
     */
    public function segment(string $text): array
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($characters);

        if ($count === 0) {
            return [];
        }

        $window = $this->dictionary->longestWord();
        $costs = array_fill(0, $count + 1, INF);
        $costs[0] = 0.0;

        /** @var array<int, array{int, string, bool, float}> $previous */
        $previous = [];

        for ($index = 0; $index < $count; $index++) {
            if ($costs[$index] === INF) {
                continue;
            }

            for ($length = 1; $length <= min($window, $count - $index); $length++) {
                $word = implode('', array_slice($characters, $index, $length));
                $known = $this->dictionary->has($word);

                if (! $known && $length > 1) {
                    continue;
                }

                $cost = $known ? $this->cost($word) : self::UNKNOWN_COST;
                $total = $costs[$index] + $cost;

                if ($total < $costs[$index + $length]) {
                    $costs[$index + $length] = $total;
                    $previous[$index + $length] = [$index, $word, $known, $cost];
                }
            }
        }

        $segments = [];
        $position = $count;

        while ($position > 0) {
            [$start, $word, $known, $cost] = $previous[$position];

            array_unshift($segments, [
                'word' => $word,
                'start' => $start,
                'length' => $position - $start,
                'known' => $known,
                'cost' => $cost,
            ]);

            $position = $start;
        }

        return $segments;
    }

    /**
     * -log probability of a word, with add-one smoothing so a dictionary word the
     * corpus never happened to use still beats an unknown character sequence.
     */
    public function cost(string $word): float
    {
        $total = max($this->dictionary->totalOccurrences(), 1);

        return -log(($this->dictionary->frequency($word) + 1) / ($total + 1));
    }
}
