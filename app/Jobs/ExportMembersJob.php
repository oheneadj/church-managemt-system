<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Email\QueueEmailAction;
use App\DTOs\OutboundEmail;
use App\Enums\ExportStatus;
use App\Enums\RecipientType;
use App\Mail\ExportReadyMail;
use App\Models\Export;
use App\Models\User;
use App\Repositories\ExportRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\MemberCsvBuilder;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

/**
 * Builds the member list CSV for one export, stores it privately and emails the staff member who asked a link that works for a
 * short time (D-064). It runs on a worker with no request, so it works as the export's church. It is safe to run twice: an
 * export that is already finished is left alone. A failure that survives the retries marks the export failed (`failed()`), and
 * nothing is sent. Only ids are logged, never a name, a phone number or a link (agreement section 17).
 */
class ExportMembersJob implements ShouldQueue
{
    use Queueable;

    /** How many times a worker tries before the export fails for good. */
    public int $tries = 3;

    /** The longest one try may take, in seconds: a big church's file takes a little while. */
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * Takes the id of the export to build.
     */
    public function __construct(public readonly string $exportId) {}

    /**
     * Builds the file, marks the export ready and emails the link, unless the export is gone or already finished.
     */
    public function handle(
        ExportRepositoryInterface $exports,
        UserRepositoryInterface $users,
        MemberCsvBuilder $builder,
        TenantContext $tenant,
        QueueEmailAction $emails,
    ): void {
        $export = $exports->find($this->exportId);

        if ($export === null || $export->status === ExportStatus::Completed) {
            return;
        }

        $church = $export->church ?? throw new LogicException('An export always belongs to a church.');
        $requester = $users->findById($export->requested_by) ?? throw new LogicException('An export is always asked for by a staff member.');

        $tenant->run($church, function () use ($exports, $builder, $emails, $export, $requester): void {
            $exports->markProcessing($export);
            $built = $builder->build($export, $requester);
            $exports->markCompleted($export, $built['path'], $built['rows']);

            $this->emailTheLink($emails, $export, $requester);
        });
    }

    /**
     * Records the failure when the retries run out, so a failed export is never left looking like it is still being built.
     */
    public function failed(?Throwable $exception = null): void
    {
        $export = app(ExportRepositoryInterface::class)->find($this->exportId);

        if ($export !== null && $export->status !== ExportStatus::Completed) {
            app(ExportRepositoryInterface::class)->markFailed($export);
        }

        Log::error('A member export failed.', ['export_id' => $this->exportId, 'church_id' => $export?->church_id, 'exception' => $exception === null ? null : $exception::class]);
    }

    /**
     * Queues the email with the signed link. The log line keeps a summary only, never the link.
     */
    private function emailTheLink(QueueEmailAction $emails, Export $export, User $requester): void
    {
        $minutes = (int) config('exports.link_minutes');
        $mail = new ExportReadyMail(
            ($export->church ?? throw new LogicException('An export always belongs to a church.'))->name,
            $export->type,
            Storage::temporaryUrl((string) $export->file_path, now()->addMinutes($minutes)),
            $minutes,
            (int) config('exports.file_days'),
        );

        $emails->execute(new OutboundEmail(
            churchId: $export->church_id,
            email: $requester->email ?? throw new LogicException('A staff member always has an email.'),
            mailable: $mail,
            messageType: 'export_ready',
            recipientType: RecipientType::User,
            logContent: $mail->redactedSummary(),
            recipientId: $requester->id,
        ));
    }
}
