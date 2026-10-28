<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use App\Services\MemberSummaries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full member record (docs/08, Get Member). The giving summary is left out for roles without
 * giving.summary, so the key is absent rather than null.
 *
 * @mixin Member
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summaries = app(MemberSummaries::class);

        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'email' => $this->email,
            'address' => $this->address,
            'nearest_landmark' => $this->nearest_landmark,
            'digital_address' => $this->digital_address,
            'marital_status' => $this->marital_status,
            'occupation' => $this->occupation,
            'tribe' => $this->tribe,
            'language_preference' => $this->language_preference,
            'educational_level' => $this->educational_level,
            'membership_type' => $this->membership_type,
            'membership_date' => $this->membership_date?->toDateString(),
            'status' => $this->status,
            'status_changed_at' => $this->status_changed_at?->toIso8601ZuluString(),
            'status_note' => $this->status_note,
            'family' => $this->whenLoaded('family', fn () => $this->family === null ? null : [
                'id' => $this->family->id,
                'name' => $this->family->name,
                'role' => $this->family_role,
            ]),
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'role' => $department->pivot?->getAttribute('role'),
            ])->values()),
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($milestone) => [
                'id' => $milestone->id,
                'type' => $milestone->type,
                'occurred_at' => $milestone->occurred_at->toDateString(),
                'officiant' => $milestone->officiant,
            ])->values()),
            'assigned_pastor' => $this->whenLoaded('assignedPastor', fn () => $this->assignedPastor === null ? null : [
                'id' => $this->assignedPastor->id,
                'name' => $this->assignedPastor->name,
            ]),
            'giving_summary' => $this->when($request->user()?->can('giving.summary') === true, fn () => $summaries->giving($this->resource)),
            'attendance_summary' => $summaries->attendance($this->resource),
            'whatsapp_opted_in' => $this->whatsapp_opted_in,
            'sms_opted_in' => $this->sms_opted_in,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
