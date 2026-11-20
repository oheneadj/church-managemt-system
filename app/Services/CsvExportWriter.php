<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Writes the rows of an export to a CSV file in private storage, one row at a time, so a large church does not fill the
 * memory (D-064). The columns are the header the builder gives; the rows come as they are read.
 *
 * A text a spreadsheet would run as a formula (it starts with =, +, - or @) is made harmless by a leading quote, so a member
 * named "=HYPERLINK(...)" cannot do anything when the file is opened. Builders pass every free text through `safe()`.
 */
class CsvExportWriter
{
    /** The characters that make a spreadsheet read a cell as a formula, and a tab or return that can hide one. */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Writes the header and then every row to the path, and returns how many rows were written.
     *
     * @param  list<string>  $header
     * @param  iterable<list<string>>  $rows
     */
    public function write(string $path, array $header, iterable $rows): int
    {
        $stream = fopen('php://temp', 'w+b') ?: throw new RuntimeException('Could not open a temporary file for the export.');

        try {
            fputcsv($stream, $header, escape: '');
            $count = 0;

            foreach ($rows as $row) {
                fputcsv($stream, $row, escape: '');
                $count++;
            }

            rewind($stream);
            Storage::writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return $count;
    }

    /**
     * The text, with a leading quote when a spreadsheet would run it as a formula.
     */
    public function safe(string $text): string
    {
        return $text !== '' && in_array($text[0], self::FORMULA_STARTS, true) ? "'{$text}" : $text;
    }
}
