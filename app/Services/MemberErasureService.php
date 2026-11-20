<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Repositories\AuditLogRepositoryInterface;
use App\Repositories\FamilyRepositoryInterface;
use App\Repositories\MemberMilestoneRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\MessageLogRepositoryInterface;

/**
 * Removes the personal details of one member everywhere they are kept, and keeps the record itself (docs/05,
 * right to erasure). The row stays, soft-deleted and archived, so giving and attendance records that point to it
 * keep working; only the details that identify the person go. It opens no transaction: the Action does.
 */
class MemberErasureService
{
    /** The columns that identify a person. Each one is emptied; the two name columns get the erased marker instead. */
    private const PERSONAL_COLUMNS = [
        'middle_name', 'preferred_name', 'date_of_birth', 'phone_secondary', 'email', 'address', 'nearest_landmark',
        'digital_address', 'photo_url', 'national_id_type', 'national_id_number', 'marital_status', 'occupation',
        'tribe', 'language_preference', 'educational_level', 'membership_date', 'status_note', 'assigned_pastor_id',
    ];

    /**
     * Takes the repositories and the photo service this service reaches.
     */
    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly FamilyRepositoryInterface $families,
        private readonly MemberMilestoneRepositoryInterface $milestones,
        private readonly MessageLogRepositoryInterface $messages,
        private readonly AuditLogRepositoryInterface $audit,
        private readonly MemberPhotoService $photos,
    ) {}

    /**
     * Erases the member's personal details from their record, photo, family, milestones, messages and history.
     */
    public function erase(Member $member): void
    {
        $marker = config('privacy.erased_marker');
        $photo = $member->photo_url;

        $this->families->releaseHead($member->id);
        $this->milestones->clearNotesFor($member->id);
        $this->messages->anonymiseRecipient($member->id);
        $this->audit->anonymiseSubject($member);

        $member->forceFill(array_fill_keys(self::PERSONAL_COLUMNS, null));
        $member->forceFill([
            'first_name' => $marker,
            'last_name' => $marker,
            'phone' => $marker,
            'sms_opted_in' => false,
            'whatsapp_opted_in' => false,
            'status' => MemberStatus::Archived,
            'status_changed_at' => now(),
        ]);

        $this->members->save($member);
        $this->members->delete($member);
        $this->photos->delete($photo);
    }
}
