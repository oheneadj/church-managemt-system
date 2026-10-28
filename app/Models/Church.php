<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChurchPlan;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant root: one row per church. Every other tenant-owned table points back here.
 *
 * @property ChurchPlan $plan
 */
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory;

    use HasUlids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'denomination',
        'address',
        'city',
        'region',
        'phone',
        'email',
        'logo_url',
        'plan',
        'plan_expires_at',
        'paystack_subaccount_code',
        'payout_method',
        'payout_network',
        'payout_account_number',
        'payout_account_name',
        'payout_verified_at',
        'sms_credits',
        'whatsapp_opted_in',
        'ussd_code',
        'timezone',
        'settings',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => ChurchPlan::class,
            'plan_expires_at' => 'immutable_datetime',
            'payout_method' => PayoutMethod::class,
            'payout_network' => MomoNetwork::class,
            'payout_account_number' => 'encrypted',
            'payout_verified_at' => 'immutable_datetime',
            'sms_credits' => 'integer',
            'whatsapp_opted_in' => 'boolean',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
