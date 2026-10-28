<?php

declare(strict_types=1);

use App\Services\WhatsApp\CheckinCommandParser;
use App\Services\WhatsApp\WhatsAppPayloadParser;

// Reading what members type and what Meta sends (docs/14 section 3). Pure logic, no database.

describe('the CHECKIN command', function (): void {
    it('is found whatever the capitals and spaces', function (string $text, ?string $code): void {
        $command = (new CheckinCommandParser)->parse($text);

        expect($command)->not->toBeNull()->and($command->serviceCode)->toBe($code);
    })->with([
        'alone' => ['CHECKIN', null],
        'lower case' => ['checkin', null],
        'spaces around' => ['  Checkin  ', null],
        'with a code' => ['CHECKIN GRA7F2', 'GRA7F2'],
        'code in lower case' => ['checkin gra7f2', 'GRA7F2'],
        'tab and new line between' => ["CHECKIN\tGRA7F2\n", 'GRA7F2'],
        'words after the code are ignored' => ['CHECKIN GRA7F2 thank you', 'GRA7F2'],
    ]);

    it('is not found in other messages', function (string $text): void {
        expect((new CheckinCommandParser)->parse($text))->toBeNull();
    })->with(['hello', '', '   ', 'CHECK IN', 'CHECKINN', 'please CHECKIN', '123456']);
});

describe('what Meta sends', function (): void {
    it('gives the text messages with a plus on the sender\'s number', function (): void {
        $messages = (new WhatsAppPayloadParser)->messages(metaPayload('CHECKIN GRA7F2', id: 'wamid.ONE'));

        expect($messages)->toHaveCount(1)
            ->and($messages[0]->id)->toBe('wamid.ONE')
            ->and($messages[0]->phone->e164)->toBe('+233244123456')
            ->and($messages[0]->text)->toBe('CHECKIN GRA7F2');
    });

    it('reads every entry and every change, not only the first', function (): void {
        $payload = metaPayload(id: 'wamid.A');
        $second = metaPayload(id: 'wamid.B', from: '233201111111');
        $payload['entry'][] = $second['entry'][0];
        $payload['entry'][0]['changes'][] = $second['entry'][0]['changes'][0] + [];
        $payload['entry'][0]['changes'][1]['value']['messages'][0]['id'] = 'wamid.C';

        $ids = collect((new WhatsAppPayloadParser)->messages($payload))->pluck('id')->all();

        expect($ids)->toBe(['wamid.A', 'wamid.C', 'wamid.B']);
    });

    it('skips what is not a usable text message', function (array $message): void {
        $payload = ['entry' => [['changes' => [['value' => ['messages' => [$message]]]]]]];

        expect((new WhatsAppPayloadParser)->messages($payload))->toBe([]);
    })->with([
        'a picture' => [['id' => 'a', 'from' => '233244123456', 'type' => 'image', 'image' => ['id' => 'x']]],
        'no id' => [['from' => '233244123456', 'type' => 'text', 'text' => ['body' => 'CHECKIN']]],
        'no sender' => [['id' => 'a', 'type' => 'text', 'text' => ['body' => 'CHECKIN']]],
        'a sender that is not a number' => [['id' => 'a', 'from' => 'abc', 'type' => 'text', 'text' => ['body' => 'CHECKIN']]],
        'no words' => [['id' => 'a', 'from' => '233244123456', 'type' => 'text']],
    ]);

    it('copes with a payload of the wrong shape', function (mixed $entry): void {
        expect((new WhatsAppPayloadParser)->messages(['entry' => $entry]))->toBe([]);
    })->with(['a string' => ['nope'], 'an empty list' => [[]], 'no changes' => [[['id' => '1']]], 'a string change' => [[['changes' => ['x']]]]]);

    it('names the kind of payload', function (): void {
        $parser = new WhatsAppPayloadParser;
        $status = ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'x']]]]]]]];

        expect($parser->eventName(metaPayload()))->toBe('message')
            ->and($parser->eventName($status))->toBe('status')
            ->and($parser->eventName(['object' => 'page']))->toBe('other');
    });
});
