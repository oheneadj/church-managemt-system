<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\DTOs\Onboarding\RegisterChurchDTO;
use App\DTOs\Onboarding\RegisteredChurch;
use App\Enums\StaffRole;
use App\Events\ChurchRegistered;
use App\Exceptions\ChurchSlugTakenException;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\ChurchSlugGenerator;
use App\Services\DefaultFundTypes;
use App\Services\OnboardingOtpService;
use App\Services\PasswordBreachScheduler;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Starts a church: checks the web address, proves the admin's phone with the texted code, then creates the
 * church and its first admin (with the email and password they will sign in with, D-054) together. It is all or nothing, so a failure never leaves half a church behind.
 */
final class RegisterChurchAction
{
    /**
     * Takes the repositories and services this action uses.
     */
    public function __construct(
        private readonly ChurchRepositoryInterface $churches,
        private readonly UserRepositoryInterface $users,
        private readonly ChurchSlugGenerator $slugs,
        private readonly OnboardingOtpService $otp,
        private readonly DefaultFundTypes $defaultFundTypes,
        private readonly PasswordBreachScheduler $breaches,
    ) {}

    /**
     * Creates the church and its admin.
     *
     * @throws ChurchSlugTakenException
     */
    public function execute(RegisterChurchDTO $dto): RegisteredChurch
    {
        $slug = $this->chooseSlug($dto);

        // The code is checked after the slug so a taken web address does not use up the code.
        $this->otp->verify($dto->adminPhone, $dto->otp);

        try {
            return DB::transaction(fn (): RegisteredChurch => $this->create($dto, $slug));
        } catch (UniqueConstraintViolationException) {
            // Someone took the same slug between the check and the insert. The unique index decides who wins.
            throw new ChurchSlugTakenException($this->slugs->suggestionsFor($slug));
        }
    }

    /**
     * The slug the person typed if it is free, otherwise an error with suggestions; or one built from the name.
     *
     * @throws ChurchSlugTakenException
     */
    private function chooseSlug(RegisterChurchDTO $dto): string
    {
        if ($dto->slug === null) {
            return $this->slugs->fromName($dto->churchName);
        }

        if (! $this->slugs->isAvailable($dto->slug)) {
            throw new ChurchSlugTakenException($this->slugs->suggestionsFor($dto->slug));
        }

        return $dto->slug;
    }

    /**
     * Saves the church, its admin and its starting fund types, and announces it. Runs inside the transaction.
     */
    private function create(RegisterChurchDTO $dto, string $slug): RegisteredChurch
    {
        $church = $this->churches->create([
            'name' => $dto->churchName,
            'slug' => $slug,
            'denomination' => $dto->denomination,
            'city' => $dto->city,
            'member_number_prefix' => $dto->memberNumberPrefix,
        ]);

        $admin = $this->users->create([
            'church_id' => $church->id,
            'name' => $dto->adminName,
            'phone' => $dto->adminPhone->e164,
            'email' => $dto->adminEmail,
        ]);
        $this->users->setPassword($admin, $dto->password);
        $admin->assignRole(StaffRole::ChurchAdmin);
        $this->defaultFundTypes->createFor($church);
        // The breach check runs later, on a worker, with only a hash of the password (D-054).
        $this->breaches->schedule($admin, $dto->password);

        ChurchRegistered::dispatch($church, $admin);

        return new RegisteredChurch($church, $admin);
    }
}
