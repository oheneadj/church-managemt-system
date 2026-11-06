<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\ExportType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a staff member that a CSV export they asked for is ready, with a signed link that works for a short time (D-064).
 * The file holds personal data, so the link is short-lived and the message log keeps `redactedSummary()`, never the link.
 * It is sent by `SendEmailJob`, never queued by itself, so one place records and retries every email.
 */
class ExportReadyMail extends Mailable
{
    /**
     * Holds what the template needs. The church name is the church's own, so it is escaped when the email is built.
     */
    public function __construct(
        public readonly string $churchName,
        public readonly ExportType $type,
        public readonly string $url,
        public readonly int $validMinutes,
        public readonly int $fileDays,
    ) {}

    /**
     * The subject names the church and what was exported.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.export_ready.subject', ['what' => $this->what(), 'church' => $this->churchName]));
    }

    /**
     * A Markdown template, from which Laravel makes both the HTML and the plain-text version.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.export-ready', with: [
            'what' => $this->what(),
            'duration' => $this->duration(),
            'days' => $this->fileDays,
        ]);
    }

    /**
     * What to keep in the message log in place of the email, so the link is never stored.
     */
    public function redactedSummary(): string
    {
        return __('emails.export_ready.summary', ['what' => $this->what()]);
    }

    /**
     * The export's name in a sentence, for example "member list".
     */
    private function what(): string
    {
        return mb_strtolower($this->type->label());
    }

    /**
     * How long the link works, in words: "1 hour", "2 hours" or "45 minutes".
     */
    private function duration(): string
    {
        return $this->validMinutes % 60 === 0
            ? trans_choice('{1} :count hour|[2,*] :count hours', intdiv($this->validMinutes, 60))
            : "{$this->validMinutes} minutes";
    }
}
