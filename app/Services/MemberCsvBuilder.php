<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExportFileBuilder;
use App\DTOs\Members\ListMembersDTO;
use App\Models\Export;
use App\Models\Member;
use App\Models\User;
use App\Repositories\MemberRepositoryInterface;

/**
 * Builds the member list as a CSV file and stores it privately (docs/08, Export Members; QA-11 TC-11-10). The columns are fixed
 * and hold what a church needs to use the list: never the national ID, the address, the landmark or the pastoral notes. The
 * members are read a few at a time and written as they come. The phone number is the one column left as it is by the formula
 * guard: it is our own validated international number, and it starts with a plus on purpose.
 */
class MemberCsvBuilder implements ExportFileBuilder
{
    /** The columns, in order. */
    private const HEADER = ['id', 'member_number', 'first_name', 'last_name', 'phone', 'email', 'status', 'gender', 'date_of_birth', 'department', 'family', 'joined_date', 'educational_level'];

    /**
     * Takes the member repository and the CSV writer.
     */
    public function __construct(private readonly MemberRepositoryInterface $members, private readonly CsvExportWriter $csv) {}

    /**
     * Writes the file for the export, for the staff member who asked, and returns the path and the number of members in it.
     */
    public function build(Export $export, User $requester): array
    {
        $filters = ListMembersDTO::fromFilters($requester, $export->filters ?? [], (int) config('limits.per_page_default'));
        $path = "churches/{$export->church_id}/exports/{$export->id}/members-".now()->toDateString().'.csv';

        $rows = (function () use ($filters) {
            foreach ($this->members->forExport($filters, (int) config('exports.chunk_size')) as $member) {
                yield $this->row($member);
            }
        })();

        return ['path' => $path, 'rows' => $this->csv->write($path, self::HEADER, $rows)];
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
            $this->csv->safe($member->member_number),
            $this->csv->safe($member->first_name),
            $this->csv->safe($member->last_name),
            $member->phone,
            $this->csv->safe((string) $member->email),
            $member->status->value,
            $member->gender->value,
            $member->date_of_birth?->toDateString() ?? '',
            $this->csv->safe($member->departments->pluck('name')->implode('; ')),
            $this->csv->safe($member->family->name ?? ''),
            $member->membership_date?->toDateString() ?? '',
            $member->educational_level->value ?? '',
        ];
    }
}
