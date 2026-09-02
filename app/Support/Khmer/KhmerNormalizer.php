<?php

namespace App\Support\Khmer;

use Normalizer;

/**
 * Brings Khmer text to a single comparable form: NFC, then SIL's Khmer
 * encoding order, so two strings that render identically compare equal.
 *
 * Nothing is stripped — a coeng or zero-width space dropped here silently
 * corrupts a word and shifts every offset downstream.
 */
class KhmerNormalizer
{
    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        return BetterKhmer::normalize($normalized === false ? $text : $normalized);
    }
}
