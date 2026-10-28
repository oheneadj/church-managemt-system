<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\DTOs\WhatsApp\CheckinCommand;
use App\DTOs\WhatsApp\CheckinOutcome;
use App\DTOs\WhatsApp\InboundWhatsAppMessage;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\Enums\CheckInMethod;
use App\Enums\CheckinReply;
use App\Enums\RecipientType;
use App\Jobs\SendWhatsAppReplyJob;
use App\Models\Member;
use App\Models\Service;
use App\Repositories\HandledWhatsappMessageRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\ServiceRepositoryInterface;
use App\Services\AttendanceRecorder;
use App\Services\ConsentRecorder;
use App\Services\TenantContext;
use App\Services\WhatsApp\CheckinCommandParser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

/**
 * Handles one WhatsApp message from a member: if it is a CHECKIN, finds the member and the open service, records
 * attendance and sends the answer (QA-05 TC-05-03 to TC-05-07). Messages that are not a CHECKIN are ignored.
 *
 * Meta delivers at least once, so the message id is stored in the same transaction as the attendance. A repeat
 * delivery finds its id already stored and does nothing, and the unique row per member and service is the second guard.
 */
final class HandleWhatsAppCheckinAction
{
    /**
     * Takes the services this action uses.
     */
    public function __construct(
        private readonly CheckinCommandParser $commands,
        private readonly HandledWhatsappMessageRepositoryInterface $handled,
        private readonly MemberRepositoryInterface $members,
        private readonly ServiceRepositoryInterface $services,
        private readonly AttendanceRecorder $recorder,
        private readonly ConsentRecorder $consents,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Handles the message. Safe to run again for the same message.
     */
    public function execute(InboundWhatsAppMessage $message): void
    {
        $command = $this->commands->parse($message->text);

        if ($command === null) {
            return;
        }

        DB::transaction(function () use ($message, $command): void {
            if (! $this->handled->claim($message->id)) {
                return;
            }

            $this->reply($message, $this->checkIn($message, $command));
        });
    }

    /**
     * Matches the sender and the service, and records attendance when both are found.
     */
    private function checkIn(InboundWhatsAppMessage $message, CheckinCommand $command): CheckinOutcome
    {
        $candidates = $this->members->findLivingByPhoneInAnyChurch($message->phone->e164);

        if ($candidates->isEmpty()) {
            return new CheckinOutcome(CheckinReply::UnknownNumber);
        }

        $service = $this->chooseService($candidates, $command);

        if ($service instanceof CheckinReply) {
            return new CheckinOutcome($service);
        }

        $member = $candidates->firstWhere('church_id', $service->church_id);

        return $member === null ? new CheckinOutcome(CheckinReply::UnknownNumber) : $this->record($service, $member);
    }

    /**
     * The service to check in to, or the reason there is none. A code names one service in one church. Without a
     * code it is the service opened first in the member's church; a number in several churches with services open
     * must say which with a code.
     *
     * @param  Collection<int, Member>  $candidates
     */
    private function chooseService(Collection $candidates, CheckinCommand $command): Service|CheckinReply
    {
        if ($command->serviceCode !== null) {
            $service = $this->services->findByCheckinCode($command->serviceCode);

            return $service !== null && $service->is_open ? $service : CheckinReply::NoOpenService;
        }

        $open = $this->services->findOpenInChurches($candidates->map(fn (Member $member): string => $member->church_id)->values()->all());

        return match (true) {
            $open->isEmpty() => CheckinReply::NoOpenService,
            $open->pluck('church_id')->unique()->count() > 1 => CheckinReply::NeedsServiceCode,
            default => $open->firstOrFail(),
        };
    }

    /**
     * Records the member at the service as that church, and counts their first message as agreeing to WhatsApp.
     */
    private function record(Service $service, Member $member): CheckinOutcome
    {
        $church = $service->church ?? throw new LogicException('A service always belongs to a church.');

        return $this->tenant->run($church, function () use ($service, $member): CheckinOutcome {
            $this->agreeToWhatsApp($member);

            $result = $this->recorder->recordMembers($service, [$member->id], CheckInMethod::Whatsapp);

            if ($result->errors !== []) {
                return new CheckinOutcome(CheckinReply::UnknownNumber);
            }

            $reply = $result->recorded === 0 ? CheckinReply::AlreadyCheckedIn : CheckinReply::CheckedIn;

            return new CheckinOutcome($reply, [
                'name' => $member->preferred_name ?? $member->first_name,
                'service' => $service->name,
                'date' => $service->service_date->format(config('whatsapp.date_format')),
            ], $member);
        });
    }

    /**
     * Messaging the church's number is how a member agrees to WhatsApp messages (docs/14, Opt-In Requirement), so the
     * choice and its consent record are set the first time.
     */
    private function agreeToWhatsApp(Member $member): void
    {
        if ($member->whatsapp_opted_in) {
            return;
        }

        $this->members->save($member->fill(['whatsapp_opted_in' => true]));
        $this->consents->sync($member, null);
    }

    /**
     * Queues the answer once the work is saved, unless this number has already had its share of answers this minute.
     */
    private function reply(InboundWhatsAppMessage $message, CheckinOutcome $outcome): void
    {
        $allowed = RateLimiter::attempt(
            'whatsapp-reply:'.$message->phone->e164,
            config('whatsapp.replies_per_minute_per_phone'),
            fn (): bool => true,
            60,
        );

        if (! $allowed) {
            return;
        }

        SendWhatsAppReplyJob::dispatch(new OutboundWhatsApp(
            churchId: $outcome->member?->church_id,
            phone: $message->phone->e164,
            text: $outcome->reply->text($outcome->values),
            messageType: 'checkin_reply',
            recipientType: $outcome->member === null ? RecipientType::Unknown : RecipientType::Member,
            recipientId: $outcome->member?->id,
        ))->onQueue('notifications')->afterCommit();
    }
}
