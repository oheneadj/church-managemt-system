<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\Member;
use App\Repositories\AuditLogRepositoryInterface;
use App\Repositories\ChurchPurgeRepositoryInterface;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the personal data of a cancelled church once its grace period is over (docs/05, D-042). Members go
 * through the same erasure as a single member, then staff, families, visitors, messages and the change history are
 * cleaned, and the church's own contact details are removed. Contributions, pledges and disbursements stay for the
 * 7-year retention rule, with the free text that could name people taken out. It opens no transaction: the Action does.
 */
class ChurchPurgeService
{
    /**
     * Takes the repositories and services this service reaches.
     */
    public function __construct(
        private readonly ChurchRepositoryInterface $churches,
        private readonly ChurchPurgeRepositoryInterface $purge,
        private readonly AuditLogRepositoryInterface $audit,
        private readonly MemberErasureService $memberErasure,
    ) {}

    /**
     * Cleans everything in the database that identifies a person of this church, then marks the church purged.
     * The church is bound as the current church while it runs, so every query is limited to it.
     */
    public function purgeData(Church $church): void
    {
        app()->instance('current.church', $church);

        try {
            $this->purge->eachIdentifiableMember(fn (Member $member) => $this->memberErasure->erase($member));
            $this->purge->anonymiseStaff();
            $this->purge->anonymiseFamilies();
            $this->purge->anonymiseVisitors();
            $this->purge->anonymiseAttendanceVisitorNames();
            $this->purge->clearContributionNotes();
            $this->purge->deleteMessageLogs();
            $this->audit->anonymiseAll();
            $this->retireChurch($church);
        } finally {
            app()->forgetInstance('current.church');
        }
    }

    /**
     * Deletes every stored file of the church: its folder of member photos, and its logo. Safe to run again.
     */
    public function deleteFiles(Church $church): void
    {
        Storage::deleteDirectory("churches/{$church->id}");

        if ($church->logo_url !== null && $church->logo_url !== '') {
            Storage::delete($church->logo_url);
        }
    }

    /**
     * Removes the church's own contact and payout details, frees its web address, and soft-deletes it.
     * The address is changed because a deleted church's slug would otherwise stay taken for ever (agreement section 5).
     */
    private function retireChurch(Church $church): void
    {
        $church->forceFill([
            'address' => null,
            'phone' => null,
            'email' => null,
            'logo_url' => null,
            'custom_domain' => null,
            'payout_method' => null,
            'payout_network' => null,
            'payout_account_number' => null,
            'payout_account_name' => null,
            'payout_verified_at' => null,
            'paystack_subaccount_code' => null,
            'slug' => "{$church->slug}-deleted-{$church->id}",
            'is_active' => false,
            'purged_at' => now()->toImmutable(),
        ]);

        $this->churches->save($church);
        $this->churches->delete($church);
    }
}
