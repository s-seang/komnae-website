<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Extracting = 'extracting';
    case Checking = 'checking';
    case Completed = 'completed';
    case NeedsOcr = 'needs_ocr';
    case LegacyEncoding = 'legacy_encoding';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::NeedsOcr, self::LegacyEncoding, self::Failed], true);
    }
}
