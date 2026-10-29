<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Counts how many parts a text message is, which is how many credits it costs (docs/14, SMS Character Limits). A text
 * made only of characters in the GSM alphabet is one part up to 160 characters and 153 per part after that; one
 * character outside it (a Twi letter such as ɔ, a long dash, an emoji) makes the whole text Unicode: one part up to 70
 * and 67 per part after that. Some GSM characters take two places (the euro sign, braces, brackets).
 */
class SmsSegmenter
{
    private const GSM_SINGLE = 160;

    private const GSM_PART = 153;

    private const UNICODE_SINGLE = 70;

    private const UNICODE_PART = 67;

    /** The GSM 03.38 basic alphabet, one place each. */
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** The GSM extension table, two places each. */
    private const GSM_EXTENDED = "\f^{}\\[~]|€";

    /**
     * How many parts (credits) this text is. An empty text is none.
     */
    public function parts(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $places = $this->gsmPlaces($text);

        if ($places !== null) {
            return $places <= self::GSM_SINGLE ? 1 : (int) ceil($places / self::GSM_PART);
        }

        $units = intdiv(strlen((string) mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

        return $units <= self::UNICODE_SINGLE ? 1 : (int) ceil($units / self::UNICODE_PART);
    }

    /**
     * How many places the text takes in the GSM alphabet, or null when any character is outside it.
     */
    private function gsmPlaces(string $text): ?int
    {
        $places = 0;

        foreach (mb_str_split($text) as $character) {
            if (mb_strpos(self::GSM_BASIC, $character) !== false) {
                $places++;
            } elseif (mb_strpos(self::GSM_EXTENDED, $character) !== false) {
                $places += 2;
            } else {
                return null;
            }
        }

        return $places;
    }
}
