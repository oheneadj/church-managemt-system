<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'auditable_type' => Member::class,
            'auditable_id' => fn () => Member::factory()->create()->getKey(),
            'event' => AuditEvent::Updated,
            'changed_fields' => ['occupation'],
            'old_values' => ['occupation' => 'Teacher'],
            'new_values' => ['occupation' => 'Nurse'],
        ];
    }
}
