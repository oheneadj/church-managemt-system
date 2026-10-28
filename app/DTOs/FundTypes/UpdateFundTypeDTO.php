<?php

declare(strict_types=1);

namespace App\DTOs\FundTypes;

use App\Http\Requests\FundTypes\UpdateFundTypeRequest;
use App\Models\FundType;

/**
 * A partial fund type update: only the fields that were sent.
 */
final readonly class UpdateFundTypeDTO
{
    /**
     * Holds the fund type and the attributes to change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public FundType $fundType, public array $attributes) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateFundTypeRequest $request): self
    {
        return new self($request->fundType(), $request->validated());
    }
}
