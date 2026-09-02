<?php

namespace App\Enums;

enum MisspellingStatus: string
{
    /** Flagged, and the user has not decided yet. */
    case Open = 'open';

    /** The user picked a replacement. The document text is left untouched. */
    case Accepted = 'accepted';

    /** The user says the word is fine as written. */
    case Ignored = 'ignored';
}
