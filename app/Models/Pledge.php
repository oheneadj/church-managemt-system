<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PledgeStatus;
use App\Traits\BelongsToChurch;
use Database\Factories\PledgeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A member's commitment to give an amount by a target date.
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
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<FundType, $this>
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
