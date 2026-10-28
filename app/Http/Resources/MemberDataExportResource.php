<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Members\MemberDataExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything held about one member, in plain terms, for a subject access request (docs/05). It lists the
 * person's own details, their family link, departments, milestones and the history of changes to their record.
 * The photo is a signed link in the member profile, so it is left out; the file itself is not part of this JSON.
 *
 * @property MemberDataExport $resource
 */
class MemberDataExportResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the export.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource->member;

        return [
            'exported_at' => $this->resource->exportedAt->toIso8601String(),
            'member' => [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'first_name' => $member->first_name,
                'middle_name' => $member->middle_name,
                'last_name' => $member->last_name,
                'preferred_name' => $member->preferred_name,
                'gender' => $member->gender->value,
                'date_of_birth' => $member->date_of_birth?->toDateString(),
                'phone' => $member->phone,
                'phone_secondary' => $member->phone_secondary,
                'email' => $member->email,
                'address' => $member->address,
                'nearest_landmark' => $member->nearest_landmark,
                'digital_address' => $member->digital_address,
                'national_id_type' => $member->national_id_type,
                'national_id_number' => $member->national_id_number,
                'marital_status' => $member->marital_status?->value,
                'occupation' => $member->occupation,
                'tribe' => $member->tribe,
                'language_preference' => $member->language_preference,
                'educational_level' => $member->educational_level?->value,
                'membership_type' => $member->membership_type->value,
                'membership_date' => $member->membership_date?->toDateString(),
                'status' => $member->status->value,
                'status_note' => $member->status_note,
                'sms_opted_in' => $member->sms_opted_in,
                'whatsapp_opted_in' => $member->whatsapp_opted_in,
                'created_at' => $member->created_at?->toIso8601String(),
            ],
            'family' => $member->family === null ? null : ['name' => $member->family->name, 'role' => $member->family_role?->value],
            'departments' => $member->departments->map(fn ($department): array => [
                'name' => $department->name,
                'role' => $department->pivot?->getAttribute('role'),
                /** @var string|null */
                'joined_at' => $department->pivot?->getAttribute('joined_at')?->toDateString(),
            ])->all(),
            'milestones' => $member->milestones->map(fn ($milestone): array => [
                'type' => $milestone->type->value,
                'occurred_at' => $milestone->occurred_at->toDateString(),
                'officiant' => $milestone->officiant,
                'cohort' => $milestone->cohort,
                'notes' => $milestone->notes,
            ])->all(),
            'consents' => $member->consents->map(fn ($consent): array => [
                'purpose' => $consent->purpose->value,
                'consented_at' => $consent->consented_at->toIso8601String(),
                'withdrawn_at' => $consent->withdrawn_at?->toIso8601String(),
                'policy_version' => $consent->policy_version,
            ])->all(),
            'history' => $member->auditLogs->map(fn ($line): array => [
                'event' => $line->event->value,
                'changed_fields' => $line->changed_fields,
                'by' => $line->actor_name,
                'reason' => $line->reason,
                'at' => $line->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
