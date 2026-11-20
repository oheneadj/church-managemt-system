<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\QrCheckedIn;
use App\DTOs\Attendance\QrCheckInDTO;
use App\Enums\CheckInMethod;
use App\Exceptions\CheckinClosedException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\InvalidQrCodeException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\ServiceRepositoryInterface;
use App\Services\AttendanceRecorder;
use App\Services\OtpService;
use App\Services\QrTokenService;
use Illuminate\Support\Facades\DB;

/**
 * Checks a member in by QR code: the code must still work, the service must be open and the texted code must be right.
 * Scanning again is harmless: the second time reports that they were already checked in.
 */
final class QrCheckInAction
{
    /**
     * Takes the services this action uses.
     */
    public function __construct(
        private readonly QrTokenService $tokens,
        private readonly ServiceRepositoryInterface $services,
        private readonly MemberRepositoryInterface $members,
        private readonly OtpService $otp,
        private readonly AttendanceRecorder $recorder,
    ) {}

    /**
     * Verifies the texted code and records the member.
     *
     * @throws InvalidQrCodeException
     * @throws CheckinClosedException
     * @throws InvalidOtpException
     * @throws TooManyOtpAttemptsException
     */
    public function execute(QrCheckInDTO $dto): QrCheckedIn
    {
        $serviceId = $this->tokens->serviceIdFor($dto->church, $dto->token);
        $service = $serviceId === null ? null : $this->services->find($serviceId);

        if ($service === null) {
            throw new InvalidQrCodeException;
        }

        if (! $service->is_open) {
            throw new CheckinClosedException;
        }

        $this->otp->check(RequestQrCheckinOtpAction::SCOPE_PREFIX.$service->id, $dto->phone, $dto->otp);

        $member = $this->members->findByPhone($dto->phone->e164) ?? throw new InvalidOtpException;

        $result = DB::transaction(fn () => $this->recorder->recordMembers($service, [$member->id], CheckInMethod::Qr));

        if ($result->errors !== []) {
            throw new InvalidOtpException;
        }

        return new QrCheckedIn($service, $member, $result->recorded === 0);
    }
}
