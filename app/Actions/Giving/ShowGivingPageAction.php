<?php

declare(strict_types=1);

namespace App\Actions\Giving;

use App\DTOs\Giving\GivingPage;
use App\DTOs\Giving\ShowGivingPageDTO;
use App\Repositories\FundTypeRepositoryInterface;
use App\Services\GivingFeeCalculator;

/**
 * Gathers what the public giving page needs (D-073). A church whose payout account the provider has not named cannot take gifts, so
 * the page is told it is not available and is given no funds to choose from, and nothing else about the church is shown.
 */
final class ShowGivingPageAction
{
    /**
     * Takes the fund repository and the fee calculator.
     */
    public function __construct(
        private readonly FundTypeRepositoryInterface $fundTypes,
        private readonly GivingFeeCalculator $fees,
    ) {}

    /**
     * Returns the page's facts for the church in the DTO.
     */
    public function execute(ShowGivingPageDTO $dto): GivingPage
    {
        $available = $dto->church->payoutStatus() === 'verified';

        return new GivingPage(
            $dto->church->name,
            $available,
            $available ? $this->fundChoices() : [],
            $this->fees->minimumGift()->amount,
            $this->fees->maximumGift()->amount,
            $this->fees->collectionFeePercent(),
        );
    }

    /**
     * The church's active funds as the plain facts a person needs to choose one.
     *
     * @return list<array{id: string, name: string, description: string|null}>
     */
    private function fundChoices(): array
    {
        $choices = [];

        foreach ($this->fundTypes->active() as $fund) {
            $choices[] = ['id' => $fund->id, 'name' => $fund->name, 'description' => $fund->description];
        }

        return $choices;
    }
}
