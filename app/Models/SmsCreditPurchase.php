<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MomoNetwork;
use App\Enums\SmsCreditPurchaseStatus;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\SmsCreditPurchaseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A church's purchase of an SMS credit package. Its id is the reference sent to the payment provider. The credits and price are
 * copies taken when it was bought.
 *
 * @property string $id
 * @property string $church_id
 * @property string $sms_package_id
 * @property string $user_id
 * @property int $credits
 * @property numeric-string $amount GHS, exact
 * @property MomoNetwork $network
 * @property string $phone
 * @property SmsCreditPurchaseStatus $status
 * @property string|null $provider_reference
 * @property numeric-string|null $provider_fee
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $completed_at
 */
class SmsCreditPurchase extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<SmsCreditPurchaseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'sms_package_id',
        'user_id',
        'credits',
        'amount',
        'network',
        'phone',
        'status',
        'provider_reference',
        'provider_fee',
        'failure_reason',
        'completed_at',
    ];

    /**
     * The package that was bought.
     *
     * @return BelongsTo<SmsPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SmsPackage::class, 'sms_package_id');
    }

    /**
     * The type each column is cast to. Amounts stay text, so no float ever touches them; the phone is encrypted.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'amount' => 'decimal:2',
            'provider_fee' => 'decimal:2',
            'network' => MomoNetwork::class,
            'phone' => 'encrypted',
            'status' => SmsCreditPurchaseStatus::class,
            'completed_at' => 'immutable_datetime',
        ];
    }
}
