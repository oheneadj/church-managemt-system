<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExportFileBuilder;
use App\DTOs\Contributions\ListContributionsDTO;
use App\Models\Contribution;
use App\Models\Export;
use App\Models\User;
use App\Repositories\ContributionRepositoryInterface;
use LogicException;

/**
 * Builds the giving records as a CSV file and stores it privately (docs/08, Export Contributions; QA-11 TC-11-11). Every gift
 * the filters find is in it, oldest first, **voided gifts included and flagged**, so the file can be audited. The columns are
 * fixed; the notes, the void reason and any phone number are never in it. Amounts are exact two-place decimal strings in cedis
 * (never a float), the date is the day the gift was given in the church's own time zone, and the fund is the name the gift was
 * recorded under, not the fund's name today. A member whose data was erased shows as deleted (the erasure replaced the name); a gift with no member as Anonymous.
 */
class ContributionCsvBuilder implements ExportFileBuilder
{
    /** The columns, in order. */
    private const HEADER = ['id', 'date', 'member_name', 'fund_type', 'amount_ghs', 'payment_method', 'reference', 'is_voided', 'recorded_by'];

    /**
     * Takes the contribution repository and the CSV writer.
     */
    public function __construct(private readonly ContributionRepositoryInterface $contributions, private readonly CsvExportWriter $csv) {}

    /**
     * Writes the file for the export and returns the path and the number of gifts in it.
     */
    public function build(Export $export, User $requester): array
    {
        $filters = ListContributionsDTO::fromFilters($export->filters ?? [], (int) config('limits.per_page_default'));
        $timezone = ($export->church ?? throw new LogicException('An export always belongs to a church.'))->timezone;
        $path = "churches/{$export->church_id}/exports/{$export->id}/contributions-".now()->toDateString().'.csv';

        $rows = (function () use ($filters, $timezone) {
            foreach ($this->contributions->forExport($filters, (int) config('exports.chunk_size')) as $gift) {
                yield $this->row($gift, $timezone);
            }
        })();

        return ['path' => $path, 'rows' => $this->csv->write($path, self::HEADER, $rows)];
    }

    /**
     * One gift as a row of the file.
     *
     * @return list<string>
     */
    private function row(Contribution $gift, string $timezone): array
    {
        return [
            $gift->id,
            $gift->contributed_at->setTimezone($timezone)->toDateString(),
            $this->csv->safe($this->memberName($gift)),
            $this->csv->safe($gift->fund_name),
            $gift->amount,
            $gift->payment_method->value,
            $this->csv->safe($this->reference($gift)),
            $gift->is_voided ? 'true' : 'false',
            $this->csv->safe($gift->recordedBy->name ?? config('privacy.erased_marker')),
        ];
    }

    /**
     * Who gave: their name, Anonymous when no member is named, or the erased marker when the member was erased.
     */
    private function memberName(Contribution $gift): string
    {
        if ($gift->member_id === null) {
            return 'Anonymous';
        }

        // An erased member is soft-deleted and loaded with the gift, so the name here is already the erased marker.
        return ($gift->member ?? throw new LogicException('A gift always has the member it names.'))->full_name;
    }

    /**
     * The payment provider's reference for a digital gift, or nothing for cash.
     */
    private function reference(Contribution $gift): string
    {
        return (string) ($gift->getAttribute('payperlez_reference') ?? $gift->getAttribute('paystack_reference') ?? $gift->getAttribute('ussd_reference') ?? '');
    }
}
