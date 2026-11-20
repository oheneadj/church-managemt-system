<?php

declare(strict_types=1);

use App\Services\SmsSegmenter;

// How many credits a text costs (docs/14, SMS Character Limits; QA-08 TC-08-05): one per part. Pure logic, no database.

dataset('texts', [
    'empty' => ['', 0],
    'one letter' => ['A', 1],
    'exactly 160 in the GSM alphabet' => [str_repeat('a', 160), 1],
    '161 in the GSM alphabet is two parts' => [str_repeat('a', 161), 2],
    'two full long parts' => [str_repeat('a', 306), 2],
    'one more than two long parts' => [str_repeat('a', 307), 3],
    'a hyphen and an apostrophe stay in the GSM alphabet' => ['Grace Assembly: GHS 150.00 received (Tithe) on 25 Oct 2026. Thank you - God bless!', 1],
    'a long dash makes it Unicode, and 71 characters are then two parts' => [str_repeat('a', 70).'–', 2],
    'a Unicode text of exactly 70 is one part' => [str_repeat('a', 69).'ɔ', 1],
    'a Twi letter makes a short text Unicode but still one part' => ['Medaase, Ɔhene', 1],
    'a Unicode text of 134 is two parts, and 135 is three' => [str_repeat('ɔ', 134), 2],
    'one more than two Unicode parts' => [str_repeat('ɔ', 135), 3],
    'an emoji counts two places' => [str_repeat('a', 69).'🙏', 2],
    'the euro sign takes two places in the GSM alphabet' => [str_repeat('a', 159).'€', 2],
    'braces and brackets take two places each' => [str_repeat('a', 158).'{}', 2],
    'a new line is one place' => [str_repeat('a', 159)."\n", 1],
]);

it('counts the parts of a text', function (string $text, int $parts): void {
    expect((new SmsSegmenter)->parts($text))->toBe($parts);
})->with('texts');
