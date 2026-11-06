<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a staff member that the password they just chose appears in a public list of passwords exposed in data breaches,
 * and asks them to choose another (D-054). It never repeats the password and has no link to click: the person goes to the
 * sign-in page and chooses "Forgot password".
 */
class PasswordBreachedMail extends Mailable
{
    /**
     * Holds the church's name, which is the church's own, so it is escaped when the email is built.
     */
    public function __construct(public readonly string $churchName) {}

    /**
     * The subject names the church and asks for a new password.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.breached.subject', ['church' => $this->churchName]));
    }

    /**
     * A Markdown template, from which Laravel makes both the HTML and the plain-text version.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.password-breached');
    }

    /**
     * What to keep in the message log in place of the email.
     */
    public function redactedSummary(): string
    {
        return __('emails.breached.summary');
    }
}
