<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditLedgerType;
use App\Traits\BelongsToChurch;
use Database\Factories\SmsCreditLedgerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail of every SMS credit movement. Append-only.
 */
class SmsCreditLedger extends Model
{
    use BelongsToChurch, HasUlids;

    /** @use HasFactory<SmsCreditLedgerFactory> */
    use HasFactory;

    protected $table = 'sms_credit_ledger';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'church_id',
        'type',
        'credits',
        'balance_after',
        'reference_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditLedgerType::class,
            'credits' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
