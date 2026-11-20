<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SmsPackageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A bundle of SMS credits a church can buy (D-069). It belongs to the platform, not to a church, so it has no church and no
 * tenant scope. A package is switched off, never deleted, because purchases point at it.
 *
 * @property string $id
 * @property string $name
 * @property int $credits
 * @property numeric-string $price GHS, exact
 * @property bool $is_active
 * @property int $sort_order
 */
class SmsPackage extends Model
{
    /** @use HasFactory<SmsPackageFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['name', 'credits', 'price', 'is_active', 'sort_order'];

    /**
     * The type each column is cast to. The price stays text, so no float ever touches it.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
