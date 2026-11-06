<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Members\ListMembersDTO;
use App\Models\Export;
use App\Models\Member;
use App\Models\User;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds the member list as a CSV file and stores it privately (docs/08, Export Members; QA-11 TC-11-10). The columns are fixed
 * and hold what a church needs to use the list: never the national ID, the address, the landmark or the pastoral notes. The
 * members are read a few at a time and written as they come, so a large church does not fill the memory.
 *
 * A text a spreadsheet would run as a formula (it starts with =, +, - or @) is made harmless by a leading quote, so a member
 * named "=HYPERLINK(...)" cannot do anything when the file is opened. The phone number is the one column left as it is: it is
 * our own validated international number, and it starts with a plus on purpose.
 */
class MemberCsvBuilder
{
    /** The columns, in order. */
    private const HEADER = ['id', 'member_number', 'first_name', 'last_name', 'phone', 'email', 'status', 'gender', 'date_of_birth', 'department', 'family', 'joined_date', 'educational_level'];

    /** The characters that make a spreadsheet read a cell as a formula, and a tab or return that can hide one. */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Takes the member repository.
     */
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    /**
     * Writes the file for the export, for the staff member who asked (so they get what they may see), and returns the path and
     * the number of members in it.
     *
     * @return array{path: string, rows: int}
     */
    public function build(Export $export, User $requester): array
    {
        $filters = ListMembersDTO::fromFilters($requester, $export->filters ?? [], (int) config('limits.per_page_default'));
        $stream = fopen('php://temp', 'w+b') ?: throw new RuntimeException('Could not open a temporary file for the export.');

        try {
            fputcsv($stream, self::HEADER, escape: '');
            $rows = 0;

            foreach ($this->members->forExport($filters, (int) config('exports.chunk_size')) as $member) {
                fputcsv($stream, $this->row($member), escape: '');
                $rows++;
            }

            $path = "churches/{$export->church_id}/exports/{$export->id}/members-".now()->toDateString().'.csv';
            rewind($stream);
            Storage::writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return ['path' => $path, 'rows' => $rows];
    }

    /**
     * One member as a row of the file.
     *
     * @return list<string>
     */
    private function row(Member $member): array
    {
        return [
            $member->id,
            $this->safe($member->member_number),
            $this->safe($member->first_name),
            $this->safe($member->last_name),
            $member->phone,
            $this->safe((string) $member->email),
            $member->status->value,
            $member->gender->value,
            $member->date_of_birth?->toDateString() ?? '',
            $this->safe($member->departments->pluck('name')->implode('; ')),
            $this->safe($member->family->name ?? ''),
            $member->membership_date?->toDateString() ?? '',
            $member->educational_level->value ?? '',
        ];
    }

    /**
     * The text, with a leading quote when a spreadsheet would run it as a formula.
     */
    private function safe(string $text): string
    {
        return $text !== '' && in_array($text[0], self::FORMULA_STARTS, true) ? "'{$text}" : $text;
    }
}
