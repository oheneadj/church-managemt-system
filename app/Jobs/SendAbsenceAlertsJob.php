<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\OutboundSms;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Models\Church;
use App\Models\Member;
use App\Repositories\AttendanceRecordRepositoryInterface;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\SmsSegmenter;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Texts the members of one church who have missed several services in a row (story 3.13, D-067). It runs once a day for each
 * church that switched the text on. Each member due a text gets a `queued` message log line and a church-paid SMS job that
 * takes the credits (D-051); a member is texted once for each stretch of absence. The job counts the credits as it goes and
 * stops when the next text could not be paid for: the rest are tried the next day. Running it twice does no harm, because
 * a member who already has a text for this stretch is no longer due one. Only ids are logged.
 */
class SendAbsenceAlertsJob implements ShouldQueue
{
    use Queueable;

    /** How many times a worker tries before the job gives up until tomorrow. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * Takes the id of the church to text for.
     */
    public function __construct(public readonly string $churchId) {}

    /**
     * Queues a text for each member who is due one, as far as the church's credits go.
     */
    public function handle(
        ChurchRepositoryInterface $churches,
        AttendanceRecordRepositoryInterface $attendance,
        MessageLogRepositoryInterface $logs,
        SmsSegmenter $segmenter,
        TenantContext $tenant,
    ): void {
        $church = $churches->find($this->churchId);

        if ($church === null || ! $church->sendsAbsenceAlerts()) {
            return;
        }

        $tenant->run($church, function () use ($church, $attendance, $logs, $segmenter): void {
            $due = $attendance->absentMembersToAlert(config('attendance.absence_service_types'), $church->absenceThreshold());
            $credits = $church->sms_credits;

            foreach ($due as $member) {
                $message = $this->messageFor($church, $member);
                $parts = $segmenter->parts($message);

                if ($parts > $credits) {
                    Log::warning('Absence alerts stopped: insufficient credits', ['church_id' => $church->id]);

                    return;
                }

                $credits -= $parts;
                $this->queueText($logs, $church, $member, $message, $parts);
            }
        });
    }

    /**
     * The text for this member: the church's wording with the name they like to be called and the church's name put in.
     */
    private function messageFor(Church $church, Member $member): string
    {
        return strtr((string) config('attendance.absence_alert_message'), [
            ':name' => $member->preferred_name ?: $member->first_name,
            ':church' => $church->name,
        ]);
    }

    /**
     * Makes the log line and queues the SMS job in one transaction, so a line never exists without its job.
     */
    private function queueText(MessageLogRepositoryInterface $logs, Church $church, Member $member, string $message, int $parts): void
    {
        DB::transaction(function () use ($logs, $church, $member, $message, $parts): void {
            $log = $logs->create([
                'church_id' => $church->id,
                'channel' => MessageChannel::Sms,
                'recipient_type' => RecipientType::Member,
                'recipient_id' => $member->id,
                'recipient_phone' => $member->phone,
                'message_type' => 'attendance_alert',
                'content' => $message,
                'status' => MessageStatus::Queued,
            ]);

            SendSmsJob::dispatch(new OutboundSms(
                churchId: $church->id,
                phone: $member->phone,
                message: $message,
                messageType: 'attendance_alert',
                recipientType: RecipientType::Member,
                recipientId: $member->id,
                messageLogId: $log->id,
                creditCost: $parts,
            ))->onQueue('notifications')->afterCommit();
        });
    }
}
