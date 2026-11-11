<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentProviderName;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\DisbursementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The automatic payout of one digital contribution to the church (D-018). Append-only financial record.
 *
 * @property string $id
 * @property string $church_id
 * @property string $contribution_id
 * @property string $gross_amount
 * @property string $platform_fee
 * @property string $requested_amount
 * @property string|null $provider_fee
 * @property string|null $net_amount
 * @property DisbursementStatus $status
 * @property PaymentProviderName $provider
 * @property string|null $provider_reference
 * @property int $attempts
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
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
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
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
     * The contribution this disbursement belongs to.
     *
     * @return BelongsTo<Contribution, $this>
     */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
