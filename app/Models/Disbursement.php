<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentProviderName;
use App\Traits\BelongsToChurch;
use Database\Factories\DisbursementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The automatic payout of one digital contribution to the church (D-018). Append-only financial record.
 */
class Disbursement extends Model
{
    use BelongsToChurch, HasUlids;

    /** @use HasFactory<DisbursementFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'contribution_id',
        'gross_amount',
        'platform_fee',
        'requested_amount',
        'provider_fee',
        'net_amount',
        'status',
        'provider',
        'provider_reference',
        'attempts',
        'failure_reason',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'provider_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => DisbursementStatus::class,
            'provider' => PaymentProviderName::class,
            'attempts' => 'integer',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Contribution, $this>
     */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
