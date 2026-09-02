<?php

namespace App\Support\Khmer;

/**
 * Finds misspellings in Khmer text.
 *
 * Looking each segmented word up in the dictionary does not work for Khmer: a
 * typo shatters into fragments that are themselves real words, so
 * សួស្តីពិភពលោគ segments to សួស្តី·ពិភព·លោ·គ and every piece "exists".
 *
 * What does work is asking whether a correction would explain the text better:
 * a span is a misspelling when some dictionary word, a couple of edits away,
 * makes the sentence markedly cheaper than the fragmented reading does.
 */
class SpellChecker
{
    /** Segments this short are suspicious enough to try correcting. */
    private const WEAK_LENGTH = 2;

    /**
     * How much cheaper a correction must be before we call the original wrong.
     * Tuned so ordinary prose (short common words sitting next to each other)
     * stays unflagged.
     */
    private const CORRECTION_MARGIN = 4.0;

    public function __construct(
        private readonly KhmerSegmenter $segmenter,
        private readonly SuggestionRanker $ranker,
    ) {}

    /**
     * @return list<array{word: string, start: int, length: int, suggestions: list<string>}>
     */
    public function check(string $text): array
    {
        $misspellings = [];

        foreach ($this->khmerRuns($text) as [$run, $runStart]) {
            foreach ($this->suspectSpans($this->segmenter->segment($run)) as $span) {
                $suggestions = $this->ranker->suggest($span['word']);
                $best = $suggestions[0] ?? null;

                if (! $span['unknown'] && ($best === null || $span['cost'] < $best['score'] + self::CORRECTION_MARGIN)) {
                    continue;
                }

                $misspellings[] = [
                    'word' => $span['word'],
                    'start' => $runStart + $span['start'],
                    'length' => $span['length'],
                    'suggestions' => array_column($suggestions, 'word'),
                ];
            }
        }

        return $misspellings;
    }

    /**
     * Maximal groups of adjacent weak segments — the only places a misspelling
     * can hide, since a correctly spelled word segments as one solid entry.
     *
     * A lone known short word is not evidence of anything: ភាគ·ឦ·សាន្ត has a
     * one-character segment in the middle and is perfectly correct. Fragmentation
     * needs at least two adjacent weak segments, or one the dictionary rejects.
     *
     * @param  list<array{word: string, start: int, length: int, known: bool, cost: float}>  $segments
     * @return list<array{word: string, start: int, length: int, cost: float, unknown: bool, segments: int}>
     */
    private function suspectSpans(array $segments): array
    {
        $spans = [];
        $current = null;

        foreach ($segments as $segment) {
            $weak = ! $segment['known'] || mb_strlen($segment['word']) <= self::WEAK_LENGTH;

            if (! $weak) {
                if ($current !== null && $this->isEvidence($current)) {
                    $spans[] = $current;
                }

                $current = null;

                continue;
            }

            if ($current === null) {
                $current = [
                    'word' => $segment['word'],
                    'start' => $segment['start'],
                    'length' => $segment['length'],
                    'cost' => $segment['cost'],
                    'unknown' => ! $segment['known'],
                    'segments' => 1,
                ];

                continue;
            }

            $current['word'] .= $segment['word'];
            $current['length'] += $segment['length'];
            $current['cost'] += $segment['cost'];
            $current['unknown'] = $current['unknown'] || ! $segment['known'];
            $current['segments']++;
        }

        if ($current !== null && $this->isEvidence($current)) {
            $spans[] = $current;
        }

        return $spans;
    }

    /**
     * @param  array{word: string, start: int, length: int, cost: float, unknown: bool, segments: int}  $span
     */
    private function isEvidence(array $span): bool
    {
        return $span['unknown'] || $span['segments'] > 1;
    }

    /**
     * Runs of Khmer letters and signs, with their character offset in the text.
     * Punctuation (។ ៗ ៛), Khmer digits, Latin and the zero-width space all end a
     * run rather than joining one.
     *
     * @return list<array{string, int}>
     */
    private function khmerRuns(string $text): array
    {
        preg_match_all('/[\x{1780}-\x{17D3}]+/u', $text, $matches, PREG_OFFSET_CAPTURE);

        $runs = [];
        $byteCursor = 0;
        $charCursor = 0;

        foreach ($matches[0] as [$run, $byteOffset]) {
            $charCursor += mb_strlen(substr($text, $byteCursor, $byteOffset - $byteCursor));
            $byteCursor = $byteOffset;

            $runs[] = [$run, $charCursor];

            $charCursor += mb_strlen($run);
            $byteCursor += strlen($run);
        }

        return $runs;
    }
}
