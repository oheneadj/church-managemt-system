<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MilestoneType;
use App\Traits\BelongsToChurch;
use Database\Factories\MemberMilestoneFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A spiritual milestone such as baptism or confirmation.
 */
class MemberMilestone extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<MemberMilestoneFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'member_id',
        'type',
        'occurred_at',
        'officiant',
        'cohort',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MilestoneType::class,
            'occurred_at' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
