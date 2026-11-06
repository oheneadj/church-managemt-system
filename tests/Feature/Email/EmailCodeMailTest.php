<?php

declare(strict_types=1);

use App\Enums\EmailCodePurpose;
use App\Mail\EmailCodeMail;

// The email that carries a 6-digit code (D-054): sign-in, password reset and staff invitation share one template.

/** The mail for one purpose, for a church and a code. */
function codeMail(EmailCodePurpose $purpose = EmailCodePurpose::SignIn, string $code = '482913'): EmailCodeMail
{
    return new EmailCodeMail('Grace Assembly', $code, $purpose, 10);
}

it('puts the code, the church and how long it lasts in both the HTML and the plain text', function (): void {
    $mail = codeMail();

    $mail->assertSeeInHtml('482913');
    $mail->assertSeeInText('482913');
    $mail->assertSeeInHtml('Grace Assembly');
    $mail->assertSeeInText('Grace Assembly');
    $mail->assertSeeInText('10 minutes');
});

it('has a subject that names the church and what the code is for', function (EmailCodePurpose $purpose, string $subject): void {
    codeMail($purpose)->assertHasSubject($subject);
})->with([
    'sign in' => [EmailCodePurpose::SignIn, 'Your sign-in code for Grace Assembly'],
    'password reset' => [EmailCodePurpose::PasswordReset, 'Your password reset code for Grace Assembly'],
    'invitation' => [EmailCodePurpose::StaffInvitation, 'Your invitation code for Grace Assembly'],
]);

it('tells an invited person where to use the code, without a link', function (): void {
    $mail = codeMail(EmailCodePurpose::StaffInvitation);

    $mail->assertSeeInText('Have an invitation code?');
    $mail->assertDontSeeInHtml('/choose-password');
});

it('tells the reader what to do if they did not ask for it, and has no button or sign-in link to click', function (): void {
    $mail = codeMail();

    $mail->assertSeeInText('If you did not ask for this code');
    $mail->assertDontSeeInHtml('class="button');
    $mail->assertDontSeeInHtml('/login');
});

it('keeps the code out of what the message log stores', function (): void {
    $summary = codeMail()->redactedSummary();

    expect($summary)->not->toContain('482913')->and($summary)->toContain('sign-in code');
});

it('escapes a church name that contains markup, so a name cannot inject HTML into the email', function (): void {
    $mail = new EmailCodeMail('<script>alert(1)</script> Church', '123456', EmailCodePurpose::SignIn, 10);

    // The second argument stops the assertion from escaping what it looks for.
    $mail->assertDontSeeInHtml('<script>', false);
    $mail->assertSeeInHtml('&lt;script&gt;', false);
});

it('never loads anything from another website, even when the app is named Laravel', function (): void {
    config(['app.name' => 'Laravel']);

    $html = (string) codeMail()->render();

    expect($html)->not->toContain('laravel.com')->and($html)->not->toContain('<img');
});
