<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Traits\BelongsToChurch;
use Carbon\CarbonImmutable;
use Database\Factories\VisitorRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A first-time visitor who is not yet a member.
 *
 * @property string $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $how_heard
 * @property CarbonImmutable $first_visit_date
 * @property CarbonImmutable|null $last_visit_date
 * @property int $visit_count
 * @property FollowUpStatus $follow_up_status
 */
class VisitorRecord extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<VisitorRecordFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'phone',
        'how_heard',
        'first_visit_service_id',
        'first_visit_date',
        'visit_count',
        'last_visit_date',
        'follow_up_status',
        'converted_member_id',
        'notes',
        'assigned_to',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_visit_date' => 'immutable_date',
            'last_visit_date' => 'immutable_date',
            'visit_count' => 'integer',
            'follow_up_status' => FollowUpStatus::class,
        ];
    }
}
