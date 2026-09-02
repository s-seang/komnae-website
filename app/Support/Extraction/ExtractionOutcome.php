<?php

namespace App\Support\Extraction;

enum ExtractionOutcome
{
    case Extracted;

    /** A PDF with no text layer — it is a scan, and OCR is not built yet. */
    case NoTextLayer;

    /**
     * The PDF uses a pre-Unicode Khmer font encoding (Limon, ABC). The bytes come
     * out as Latin mojibake, so checking them would be nonsense.
     */
    case LegacyEncoding;

    /**
     * Text came out, but it is not Khmer — a Latin-only PDF, or a decode that
     * produced letters from the wrong script. Nothing here to spell-check.
     */
    case NoKhmerText;
}
