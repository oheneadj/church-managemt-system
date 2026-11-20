<?php

declare(strict_types=1);

use App\Enums\CheckinReply;

// The WhatsApp answers (QA-05 TC-05-03 to TC-05-07). The wording is in config/whatsapp.php.

it('fills the name, service and date into the welcome', function (): void {
    expect(CheckinReply::CheckedIn->text(['name' => 'Emmanuel', 'service' => 'Sunday Morning Service', 'date' => '25 Oct 2026']))
        ->toBe('✅ Checked in to Sunday Morning Service — 25 Oct 2026. Welcome, Emmanuel! God bless you. 🙏');
});

it('has wording and a label for every reply', function (CheckinReply $reply): void {
    expect($reply->text())->not->toBeEmpty()->and($reply->label())->not->toBeEmpty();
})->with(CheckinReply::cases());

it('reads the wording from config, so it can be changed in one place', function (): void {
    config(['whatsapp.replies.no_open_service' => 'Nothing is open.']);

    expect(CheckinReply::NoOpenService->text())->toBe('Nothing is open.');
});
