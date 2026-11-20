<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailCodePurpose;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email that carries a 6-digit code: a sign-in code, a password reset code or a staff invitation code (D-054). It
 * is sent by `SendEmailJob`, never queued by itself, so one place records and retries every email. It holds a code, so
 * what the message log keeps is `redactedSummary()`, not this text.
 */
class EmailCodeMail extends Mailable
{
    /**
     * Holds what the template needs. The church name is the church's own, so it is escaped when the email is built.
     */
    public function __construct(
        public readonly string $churchName,
        public readonly string $code,
        public readonly EmailCodePurpose $purpose,
        public readonly int $validMinutes,
    ) {}

    /**
     * The subject names the church and what the code is for.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.code.subject', ['purpose' => $this->purpose->label(), 'church' => $this->churchName]));
    }

    /**
     * A Markdown template, from which Laravel makes both the HTML and the plain-text version.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.email-code', with: [
            'intro' => __('emails.code.intro.'.$this->purpose->value, ['church' => $this->churchName]),
        ]);
    }

    /**
     * What to keep in the message log in place of the email, so the code is never stored.
     */
    public function redactedSummary(): string
    {
        return __('emails.code.summary', ['purpose' => $this->purpose->label()]);
    }
}
