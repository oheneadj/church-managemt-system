<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentPurpose;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One agreement by a member to receive a kind of message. It stays after withdrawal, with the time it ended.
 *
 * @property string $id
 * @property string $church_id
 * @property string $member_id
 * @property ConsentPurpose $purpose
 * @property CarbonImmutable $consented_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property string $policy_version
 */
class Consent extends Model
{
    use BelongsToChurch, HasUlids;

    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'member_id',
        'purpose',
        'consented_at',
        'withdrawn_at',
        'policy_version',
        'recorded_by',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'consented_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    /**
     * The member who agreed.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
