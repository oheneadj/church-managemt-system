<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\BelongsToChurch;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single giving transaction. Never deleted; mistakes are voided with a reason.
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
        'pledge_id',
        'amount',
        'processing_fee',
        'total_paid',
        'payment_method',
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
        'contributed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'is_voided' => 'boolean',
            'voided_at' => 'immutable_datetime',
            'contributed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<FundType, $this>
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * @return BelongsTo<Pledge, $this>
     */
    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    /**
     * @return HasOne<Disbursement, $this>
     */
    public function disbursement(): HasOne
    {
        return $this->hasOne(Disbursement::class);
    }
}
