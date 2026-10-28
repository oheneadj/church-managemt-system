<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToChurch;
use Database\Factories\FundTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A giving category such as tithe or building fund.
 */
class FundType extends Model
{
    use BelongsToChurch, HasUlids, SoftDeletes;

    /** @use HasFactory<FundTypeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * The type each column is cast to: enums, dates, booleans and encrypted fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
