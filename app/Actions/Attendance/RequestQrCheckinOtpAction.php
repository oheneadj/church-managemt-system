<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\RequestQrCheckinOtpDTO;
use App\DTOs\OutboundSms;
use App\Enums\MemberStatus;
use App\Enums\RecipientType;
use App\Exceptions\CheckinClosedException;
use App\Exceptions\InvalidQrCodeException;
use App\Jobs\SendSmsJob;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\ServiceRepositoryInterface;
use App\Services\OtpService;
use App\Services\QrTokenService;
use Illuminate\Support\Facades\Hash;

/**
 * Texts a check-in code to a member who scanned the QR code. It answers the same whether or not the phone number belongs
 * to a member, so a stranger cannot find out who attends this church. The code proves the person holds the phone, and
 * the QR code, which only works in front of the screen, proves they are at the venue.
 */
final class RequestQrCheckinOtpAction
{
    /** The prefix that keeps check-in codes apart from sign-in codes in the cache. */
    public const SCOPE_PREFIX = 'qr:';

    /**
     * Takes the services this action uses.
     */
    public function __construct(
        private readonly QrTokenService $tokens,
        private readonly ServiceRepositoryInterface $services,
        private readonly MemberRepositoryInterface $members,
        private readonly OtpService $otp,
    ) {}

    /**
     * Sends the code if the phone belongs to a member who can be checked in.
     *
     * @throws InvalidQrCodeException
     * @throws CheckinClosedException
     */
    public function execute(RequestQrCheckinOtpDTO $dto): void
    {
        $serviceId = $this->tokens->serviceIdFor($dto->church, $dto->token);
        $service = $serviceId === null ? null : $this->services->find($serviceId);

        if ($service === null) {
            throw new InvalidQrCodeException;
        }

        if (! $service->is_open) {
            throw new CheckinClosedException;
        }

        $member = $this->members->findByPhone($dto->phone->e164);

        if ($member === null || $member->status === MemberStatus::Deceased) {
            // One hash anyway, so an unknown number takes as long as a known one.
            Hash::make('unused');

            return;
        }

        $code = $this->otp->issue(self::SCOPE_PREFIX.$service->id, $dto->phone);

        SendSmsJob::dispatch(new OutboundSms(
            churchId: $dto->church->id,
            phone: $dto->phone->e164,
            message: $this->otp->message($code),
            messageType: 'checkin_otp',
            recipientType: RecipientType::Member,
            recipientId: $member->id,
            logContent: '[otp redacted]',
        ))->onQueue('critical');
    }
}
