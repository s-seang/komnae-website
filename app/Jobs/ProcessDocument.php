<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\MisspellingStatus;
use App\Models\Document;
use App\Support\Extraction\DocumentTextExtractor;
use App\Support\Extraction\ExtractionOutcome;
use App\Support\Khmer\KhmerNormalizer;
use App\Support\Khmer\SpellChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs the pipeline for one document: extract → normalise → check → persist.
 *
 * Uploads go through the queue; pasted text runs this synchronously, so the
 * stages stay in one place rather than being duplicated in the controller.
 */
class ProcessDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Document $document) {}

    public function handle(
        DocumentTextExtractor $extractor,
        KhmerNormalizer $normalizer,
        SpellChecker $checker,
    ): void {
        $text = (string) $this->document->text;

        if ($this->document->path !== null) {
            $this->document->update(['status' => DocumentStatus::Extracting]);

            $extracted = $extractor->extract(
                Storage::disk('local')->path($this->document->path),
                (string) $this->document->mime,
            );

            if ($extracted['outcome'] !== ExtractionOutcome::Extracted) {
                $this->document->update($this->unusable($extracted['outcome']));

                return;
            }

            $text = $extracted['text'];
        }

        $text = $normalizer->normalize($text);

        $this->document->update([
            'status' => DocumentStatus::Checking,
            'text' => $text,
        ]);

        $this->document->misspellings()->delete();

        $this->document->misspellings()->createMany(array_map(
            fn (array $misspelling): array => [...$misspelling, 'status' => MisspellingStatus::Open],
            $checker->check($text),
        ));

        $this->document->update(['status' => DocumentStatus::Completed]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->document->update([
            'status' => DocumentStatus::Failed,
            'error' => 'Something went wrong while reading this document.',
        ]);
    }

    /**
     * Why a file produced nothing checkable, in words the user can act on.
     *
     * @return array{status: DocumentStatus, error: string}
     */
    private function unusable(ExtractionOutcome $outcome): array
    {
        return match ($outcome) {
            ExtractionOutcome::NoTextLayer => [
                'status' => DocumentStatus::NeedsOcr,
                'error' => 'This PDF has no text layer, so it is a scan. Reading scans needs OCR, which is not built yet.',
            ],
            ExtractionOutcome::LegacyEncoding => [
                'status' => DocumentStatus::LegacyEncoding,
                'error' => 'This PDF uses a pre-Unicode Khmer font (Limon or ABC). Convert it to Unicode Khmer, then upload it again.',
            ],
            default => [
                'status' => DocumentStatus::Failed,
                'error' => 'No Khmer text was found in this file.',
            ],
        };
    }
}
