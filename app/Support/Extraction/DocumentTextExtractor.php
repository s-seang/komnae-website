<?php

namespace App\Support\Extraction;

use Illuminate\Support\Facades\Process;

/**
 * Pulls text out of an uploaded file.
 *
 * PDFs go through poppler's `pdftotext`, always as an argument array — a
 * user-supplied name never reaches a shell. Scanned PDFs carry no text layer and
 * are reported as such rather than checked as an empty string; OCR is a separate
 * stage that does not exist yet.
 */
class DocumentTextExtractor
{
    /** Below this share of Khmer letters there is nothing here worth checking. */
    private const MINIMUM_KHMER_RATIO = 0.2;

    /**
     * Pre-Unicode Khmer font families. Text drawn with these decodes to Latin
     * mojibake, so the file needs converting before it can be checked at all.
     */
    private const LEGACY_FONTS = ['limon', 'abc-', 'khek'];

    /**
     * @return array{outcome: ExtractionOutcome, text: string}
     */
    public function extract(string $path, string $mime): array
    {
        $isPdf = $mime === 'application/pdf';

        if ($isPdf && $this->usesLegacyFonts($path)) {
            return ['outcome' => ExtractionOutcome::LegacyEncoding, 'text' => ''];
        }

        $text = $isPdf ? $this->fromPdf($path) : (string) file_get_contents($path);

        if (trim($text) === '') {
            return ['outcome' => ExtractionOutcome::NoTextLayer, 'text' => ''];
        }

        if (! $this->hasEnoughKhmer($text)) {
            return ['outcome' => ExtractionOutcome::NoKhmerText, 'text' => $text];
        }

        return ['outcome' => ExtractionOutcome::Extracted, 'text' => $text];
    }

    private function fromPdf(string $path): string
    {
        $result = Process::run(['pdftotext', '-enc', 'UTF-8', '-nopgbrk', $path, '-']);

        return $result->successful() ? $result->output() : '';
    }

    private function usesLegacyFonts(string $path): bool
    {
        $fonts = mb_strtolower(Process::run(['pdffonts', $path])->output());

        foreach (self::LEGACY_FONTS as $font) {
            if (str_contains($fonts, $font)) {
                return true;
            }
        }

        return false;
    }

    private function hasEnoughKhmer(string $text): bool
    {
        $letters = preg_match_all('/[\p{L}\x{1780}-\x{17FF}]/u', $text);

        if ($letters === 0) {
            return false;
        }

        return preg_match_all('/[\x{1780}-\x{17FF}]/u', $text) / $letters >= self::MINIMUM_KHMER_RATIO;
    }
}
