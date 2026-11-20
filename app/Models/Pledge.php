<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PledgeStatus;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\PledgeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A member's commitment to give an amount by a target date.
 *
 * @property string $id
 * @property string $church_id
 * @property string $member_id
 * @property string $fund_type_id
 * @property string $description
 * @property numeric-string $amount_pledged
 * @property numeric-string $amount_paid
 * @property CarbonImmutable $target_date
 * @property PledgeStatus $status
 * @property string $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read numeric-string $amount_outstanding what is still to be paid, never below 0
 * @property-read int<0, 100> $progress_percent how much is paid, from 0 to 100
 * @property-read Member $member
 * @property-read FundType $fundType
 */
class Pledge extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<PledgeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'member_id',
        'fund_type_id',
        'description',
        'amount_pledged',
        'amount_paid',
        'target_date',
        'status',
        'created_by',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_pledged' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'target_date' => 'immutable_date',
            'status' => PledgeStatus::class,
        ];
    }

    /**
     * The member this pledge belongs to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The fund type this pledge belongs to.
     *
     * @return BelongsTo<FundType, $this>
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * The contribution records of this pledge.
     *
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * What is still to be paid: the pledge less what was paid, and 0 once it is paid in full or over.
     *
     * @return Attribute<numeric-string, never>
     */
    protected function amountOutstanding(): Attribute
    {
        return Attribute::get(fn (): string => bccomp($this->amount_paid, $this->amount_pledged, 2) >= 0 ? '0.00' : bcsub($this->amount_pledged, $this->amount_paid, 2));
    }

    /**
     * How much of the pledge is paid, as a whole number from 0 to 100 (a part of a percent is not shown).
     *
     * @return Attribute<int<0, 100>, never>
     */
    protected function progressPercent(): Attribute
    {
        return Attribute::get(fn (): int => bccomp($this->amount_pledged, '0', 2) <= 0 ? 0 : max(0, min(100, (int) bcdiv(bcmul($this->amount_paid, '100', 2), $this->amount_pledged, 0))));
    }
}
