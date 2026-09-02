<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

#[Signature('komnot:build-dictionary {--keep-cache : Reuse previously downloaded sources}')]
#[Description('Rebuild the Khmer word list, confusion pairs, and frequency data in database/data')]
class BuildDictionary extends Command
{
    private const WORDS_URL = 'https://raw.githubusercontent.com/sbbic/sbbic-khmer-spelling-checker-for-ms-word/master/KhmerSpellCheck/bin/Debug/km_KH.dic';

    private const AFFIXES_URL = 'https://raw.githubusercontent.com/sbbic/sbbic-khmer-spelling-checker-for-ms-word/master/KhmerSpellCheck/bin/Debug/km_KH.aff';

    private const CORPUS_URL = 'https://raw.githubusercontent.com/phylypo/khmer-text-data/master/km-wikipedia/seg_kmwiki_data.zip';

    public function handle(): int
    {
        $target = database_path('data');
        $cache = storage_path('app/dictionary-build');

        File::ensureDirectoryExists($target);
        File::ensureDirectoryExists($cache);

        $words = $this->buildWords($cache, $target);
        $this->buildConfusions($cache, $target);
        $this->buildFrequencies($cache, $target, $words);

        if (! $this->option('keep-cache')) {
            File::deleteDirectory($cache);
        }

        $this->components->info('Dictionary data written to database/data.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function buildWords(string $cache, string $target): array
    {
        $raw = $this->download(self::WORDS_URL, $cache.'/km_KH.dic');

        $words = [];

        foreach (explode("\n", $raw) as $index => $line) {
            if ($index === 0) {
                continue;
            }

            $word = trim(preg_replace('~/.*$~', '', $line));
            $word = ltrim($word, "\u{feff}");

            if ($word !== '') {
                $words[$word] = true;
            }
        }

        ksort($words);

        File::put($target.'/khmer-words.txt', implode("\n", array_keys($words))."\n");
        $this->components->twoColumnDetail('khmer-words.txt', number_format(count($words)).' words');

        return $words;
    }

    /**
     * The `.aff` REP table holds hand-curated Khmer confusion pairs (ក↔គ, ិ↔ី, …)
     * that make far better suggestion candidates than blind edit distance.
     */
    private function buildConfusions(string $cache, string $target): void
    {
        $raw = $this->download(self::AFFIXES_URL, $cache.'/km_KH.aff');

        $pairs = [];

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^REP\s+(\S+)\s+(\S+)\s*$/u', trim($line), $matches) === 1) {
                $pairs[] = $matches[1].','.$matches[2];
            }
        }

        File::put($target.'/khmer-confusions.txt', implode("\n", $pairs)."\n");
        $this->components->twoColumnDetail('khmer-confusions.txt', count($pairs).' pairs');
    }

    /**
     * @param  array<string, true>  $words
     */
    private function buildFrequencies(string $cache, string $target, array $words): void
    {
        $archive = $cache.'/kmwiki.zip';

        if (! File::exists($archive)) {
            $this->components->task('downloading corpus', fn () => Http::timeout(300)->sink($archive)->get(self::CORPUS_URL)->successful());
        }

        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Unable to open the corpus archive.');
        }

        $counts = [];
        $progress = $this->output->createProgressBar($zip->numFiles);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $progress->advance();

            $name = $zip->getNameIndex($index);

            if ($name === false || ! str_ends_with($name, '.txt')) {
                continue;
            }

            $tokens = preg_split('/\s+/u', (string) $zip->getFromIndex($index), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($tokens ?: [] as $token) {
                if (isset($words[$token])) {
                    $counts[$token] = ($counts[$token] ?? 0) + 1;
                }
            }
        }

        $zip->close();
        $progress->finish();
        $this->newLine();

        arsort($counts);

        $lines = [];

        foreach ($counts as $word => $count) {
            $lines[] = $word.','.$count;
        }

        File::put($target.'/khmer-frequencies.csv', implode("\n", $lines)."\n");
        $this->components->twoColumnDetail('khmer-frequencies.csv', number_format(count($counts)).' words with counts');
    }

    private function download(string $url, string $path): string
    {
        if (File::exists($path)) {
            return File::get($path);
        }

        $body = Http::timeout(120)->throw()->get($url)->body();

        File::put($path, $body);

        return $body;
    }
}
