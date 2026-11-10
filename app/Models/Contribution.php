<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContributionStatus;
use App\Enums\PaymentMethod;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single giving transaction. Never deleted; mistakes are voided with a reason.
 *
 * @property string $id
 * @property string $church_id
 * @property string|null $member_id
 * @property string|null $service_id
 * @property string|null $pledge_id
 * @property string $fund_type_id
 * @property string $fund_name the fund's name when the gift was recorded; a later rename does not change it
 * @property string $amount
 * @property string|null $processing_fee what the giver paid on top of the gift for the collection (null for cash)
 * @property string|null $total_paid the gift and the processing fee together (null for cash)
 * @property string|null $payperlez_reference the provider's reference for the collection, once it has one
 * @property PaymentMethod $payment_method
 * @property ContributionStatus $status
 * @property bool $is_voided
 * @property CarbonImmutable|null $voided_at
 * @property string|null $voided_by
 * @property string|null $void_reason
 * @property string|null $recorded_by the staff member who recorded it; empty for a gift given on the public giving page (D-073)
 * @property string|null $giver_name the name a giver typed on the public page, kept encrypted
 * @property string|null $payer_phone_hash a keyed hash of the phone paid from, never the number
 * @property string|null $notes
 * @property CarbonImmutable $contributed_at
 * @property CarbonImmutable|null $receipt_queued_at
 * @property CarbonImmutable|null $created_at
 * @property-read FundType $fundType
 * @property-read User|null $recordedBy
 */
class Contribution extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<ContributionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'member_id',
        'service_id',
        'fund_type_id',
        'fund_name',
        'pledge_id',
        'amount',
        'processing_fee',
        'total_paid',
        'payment_method',
        'status',
        'paystack_reference',
        'paystack_status',
        'payperlez_reference',
        'ussd_reference',
        'is_voided',
        'voided_at',
        'voided_by',
        'void_reason',
        'recorded_by',
        'notes',
        'giver_name',
        'payer_phone_hash',
        'contributed_at',
        'receipt_queued_at',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => ContributionStatus::class,
            'giver_name' => 'encrypted',
            'is_voided' => 'boolean',
            'voided_at' => 'immutable_datetime',
            'contributed_at' => 'immutable_datetime',
            'receipt_queued_at' => 'immutable_datetime',
        ];
    }

    /**
     * Only the gifts that count: paid and not voided. Every total, list, pledge sum, receipt and payout uses this, so a pending or
     * failed digital gift is never added up (D-053).
     *
     * @param  Builder<Contribution>  $query
     */
    #[Scope]
    protected function counted(Builder $query): void
    {
        $query->where('status', ContributionStatus::Completed->value)->where('is_voided', false);
    }

    /**
     * The member this contribution belongs to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The service this contribution belongs to.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The fund type this contribution belongs to.
     *
     * @return BelongsTo<FundType, $this>
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * The pledge this contribution belongs to.
     *
     * @return BelongsTo<Pledge, $this>
     */
    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    /**
     * The one disbursement of this contribution.
     *
     * @return HasOne<Disbursement, $this>
     */
    public function disbursement(): HasOne
    {
        return $this->hasOne(Disbursement::class);
    }

    /**
     * The staff member who recorded or confirmed this gift.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The staff member who voided this gift, if it was voided.
     *
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
