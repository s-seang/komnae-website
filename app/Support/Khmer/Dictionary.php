<?php

namespace App\Support\Khmer;

use RuntimeException;

/**
 * The Khmer word list, its corpus frequencies, and the confusion pairs used to
 * rank suggestions. Data, not code — see database/data/README.md for sources
 * and `php artisan komnot:build-dictionary` to rebuild it.
 */
class Dictionary
{
    /** @var array<string, true>|null */
    private ?array $words = null;

    /** @var array<string, int>|null */
    private ?array $frequencies = null;

    /** @var list<array{string, string}>|null */
    private ?array $confusions = null;

    /** @var list<string>|null */
    private ?array $encodedWords = null;

    /** @var list<string>|null */
    private ?array $encodedIndex = null;

    /** @var array<string, string> */
    private array $byteMap = [];

    private int $longestWord = 1;

    private int $totalOccurrences = 0;

    public function __construct(private readonly string $path)
    {
        //
    }

    public function has(string $word): bool
    {
        return isset($this->words()[$word]);
    }

    /**
     * Corpus occurrences. Dictionary words the corpus never used return 0 —
     * callers smooth, they do not treat that as "not a word".
     */
    public function frequency(string $word): int
    {
        return $this->frequencies()[$word] ?? 0;
    }

    public function totalOccurrences(): int
    {
        $this->frequencies();

        return $this->totalOccurrences;
    }

    /**
     * Character length of the longest entry, which bounds the segmenter's lookahead.
     */
    public function longestWord(): int
    {
        $this->words();

        return $this->longestWord;
    }

    /**
     * @return list<array{string, string}>
     */
    public function confusionPairs(): array
    {
        if ($this->confusions !== null) {
            return $this->confusions;
        }

        $this->confusions = [];

        foreach ($this->lines('khmer-confusions.txt') as $line) {
            $pair = explode(',', $line);

            if (count($pair) === 2 && $pair[0] !== '' && $pair[1] !== '') {
                $this->confusions[] = [$pair[0], $pair[1]];
            }
        }

        return $this->confusions;
    }

    /**
     * Dictionary words within `$maxDistance` edits of `$word`.
     *
     * Khmer uses barely a hundred distinct codepoints here, so each one maps to
     * a single byte and PHP's native levenshtein() then counts characters rather
     * than UTF-8 bytes. A full 75k scan costs a few milliseconds, which is why
     * there is no SymSpell index or BK-tree in this project.
     *
     * @return array<string, int> word => edit distance
     */
    public function nearest(string $word, int $maxDistance = 2): array
    {
        $this->buildEncodedIndex();

        $needle = $this->encode($word);
        $length = strlen($needle);
        $matches = [];

        foreach ($this->encodedWords as $index => $encoded) {
            if (abs(strlen($encoded) - $length) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($needle, $encoded);

            if ($distance <= $maxDistance) {
                $matches[$this->encodedIndex[$index]] = $distance;
            }
        }

        return $matches;
    }

    private function encode(string $word): string
    {
        $encoded = '';

        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (! isset($this->byteMap[$character])) {
                // ponytail: 255 distinct characters is plenty for Khmer; anything
                // past that collides on one byte rather than growing an index.
                $next = count($this->byteMap) + 1;
                $this->byteMap[$character] = chr(min($next, 255));
            }

            $encoded .= $this->byteMap[$character];
        }

        return $encoded;
    }

    private function buildEncodedIndex(): void
    {
        if ($this->encodedWords !== null) {
            return;
        }

        $this->encodedWords = [];
        $this->encodedIndex = [];

        foreach (array_keys($this->words()) as $word) {
            $this->encodedIndex[] = (string) $word;
            $this->encodedWords[] = $this->encode((string) $word);
        }
    }

    /**
     * @return array<string, true>
     */
    private function words(): array
    {
        if ($this->words !== null) {
            return $this->words;
        }

        $this->words = [];

        foreach ($this->lines('khmer-words.txt') as $word) {
            $this->words[$word] = true;
            $this->longestWord = max($this->longestWord, mb_strlen($word));
        }

        return $this->words;
    }

    /**
     * @return array<string, int>
     */
    private function frequencies(): array
    {
        if ($this->frequencies !== null) {
            return $this->frequencies;
        }

        $this->frequencies = [];

        foreach ($this->lines('khmer-frequencies.csv') as $line) {
            $separator = strrpos($line, ',');

            if ($separator === false) {
                continue;
            }

            $count = (int) substr($line, $separator + 1);
            $this->frequencies[substr($line, 0, $separator)] = $count;
            $this->totalOccurrences += $count;
        }

        return $this->frequencies;
    }

    /**
     * @return list<string>
     */
    private function lines(string $file): array
    {
        $path = $this->path.'/'.$file;

        if (! is_readable($path)) {
            throw new RuntimeException("Missing dictionary data file [{$file}]. Run: php artisan komnot:build-dictionary");
        }

        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }
}
