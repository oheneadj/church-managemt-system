<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StaffPermission;
use App\Models\Member;
use App\Services\MemberPhotoService;
use App\Services\MemberSummaries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full member record (docs/08, Get Member). The giving summary is left out for roles without
 * giving.summary, so the key is absent rather than null. Every enum is sent as its value plus a _label.
 *
 * @mixin Member
 */
class MemberResource extends JsonResource
{
    /**
     * Shapes the member for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'email' => $this->email,
            'photo_url' => app(MemberPhotoService::class)->url($this->photo_url),
            'address' => $this->address,
            'nearest_landmark' => $this->nearest_landmark,
            'digital_address' => $this->digital_address,
            'marital_status' => $this->marital_status?->value,
            'marital_status_label' => $this->marital_status?->label(),
            'occupation' => $this->occupation,
            'tribe' => $this->tribe,
            'language_preference' => $this->language_preference,
            'educational_level' => $this->educational_level?->value,
            'educational_level_label' => $this->educational_level?->label(),
            'membership_type' => $this->membership_type->value,
            'membership_type_label' => $this->membership_type->label(),
            'membership_date' => $this->membership_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'status_note' => $this->status_note,
            'family' => $this->whenLoaded('family', fn () => $this->family === null ? null : new MemberFamilyResource($this->resource)),
            'departments' => $this->whenLoaded('departments', fn () => MemberDepartmentResource::collection($this->departments)),
            'milestones' => $this->whenLoaded('milestones', fn () => MemberMilestoneResource::collection($this->milestones)),
            'assigned_pastor' => $this->whenLoaded('assignedPastor', fn () => $this->assignedPastor === null ? null : new StaffSummaryResource($this->assignedPastor)),
            'giving_summary' => $this->when($request->user()?->can(StaffPermission::GivingSummary->value) === true, fn () => new GivingSummaryResource(app(MemberSummaries::class)->giving($this->resource))),
            'attendance_summary' => app(MemberSummaries::class)->attendance($this->resource),
            'whatsapp_opted_in' => $this->whatsapp_opted_in,
            'sms_opted_in' => $this->sms_opted_in,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
