<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberDepartment>
 */
class MemberDepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'member_id' => fn (array $attributes) => Member::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'department_id' => fn (array $attributes) => Department::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
        ];
    }
}
