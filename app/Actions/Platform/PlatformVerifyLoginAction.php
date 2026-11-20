<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\DTOs\Platform\PlatformVerifyLoginDTO;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Models\PlatformAdmin;
use App\Repositories\PlatformAdminRepositoryInterface;
use App\Services\Platform\PlatformLoginCodes;
use App\Services\Platform\PlatformSessionService;

/**
 * Step two of platform admin sign-in (D-069): checks the emailed code and signs the browser in. The code is checked before
 * the account is looked up, so an unknown email, a switched-off account and a wrong code all fail the same way.
 *
 * @throws InvalidOtpException
 * @throws TooManyOtpAttemptsException
 */
final class PlatformVerifyLoginAction
{
    /**
     * Takes the account lookup, the code store and the session service.
     */
    public function __construct(
        private readonly PlatformAdminRepositoryInterface $admins,
        private readonly PlatformLoginCodes $codes,
        private readonly PlatformSessionService $sessions,
    ) {}

    /**
     * Completes sign-in and returns the admin.
     */
    public function execute(PlatformVerifyLoginDTO $dto): PlatformAdmin
    {
        $this->codes->check($dto->email, $dto->code);

        $admin = $this->admins->findByEmail($dto->email);

        if ($admin === null || ! $admin->is_active) {
            throw new InvalidOtpException;
        }

        $this->sessions->start($admin, $dto->session);

        return $admin;
    }
}
