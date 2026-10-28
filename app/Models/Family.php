<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToChurch;
use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Groups members into a household.
 *
 * @property string $id
 * @property string $church_id
 * @property string $name
 * @property string|null $notes
 * @property string|null $head_member_id
 */
class Family extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<FamilyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'head_member_id',
        'notes',
    ];

    /**
     * The member this family belongs to.
     *
     * @return BelongsTo<Member, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'head_member_id');
    }

    /**
     * The member records of this family.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
