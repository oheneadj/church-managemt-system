<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Chooses the subdomain slug of a new church: builds one from the church name and checks that a slug is free.
 * A slug is free when no church (even a deleted one) holds it and it is not kept for the platform.
 */
class ChurchSlugGenerator
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Builds a free slug from the church name. "Grace Assembly International" becomes grace-assembly-international,
     * and a name that is taken gets -2, -3 and so on.
     */
    public function fromName(string $name): string
    {
        $base = $this->cleanBase(Str::slug($name));

        for ($number = 1; ; $number++) {
            $candidate = $number === 1 ? $base : $this->withEnding($base, (string) $number);

            if ($this->isAvailable($candidate)) {
                return $candidate;
            }
        }
    }

    /**
     * Whether a church may take this slug.
     */
    public function isAvailable(string $slug): bool
    {
        return ! in_array($slug, config('onboarding.reserved_slugs'), true) && ! $this->churches->slugExists($slug);
    }

    /**
     * Free slugs close to a taken one, to offer in the error message (QA-03 TC-03-02).
     *
     * @return list<string>
     */
    public function suggestionsFor(string $slug): array
    {
        $suggestions = [];

        foreach (config('onboarding.slug_suggestion_suffixes') as $suffix) {
            $candidate = $this->withEnding($slug, $suffix);

            if ($this->isAvailable($candidate)) {
                $suggestions[] = $candidate;
            }
        }

        return $suggestions;
    }

    /**
     * Cuts a slug to the allowed length and pads one that is too short, so even a very short name gets a valid slug.
     */
    private function cleanBase(string $slug): string
    {
        $slug = rtrim(Str::limit($slug, config('onboarding.slug_max_length'), ''), '-');

        return strlen($slug) < config('onboarding.slug_min_length') ? $slug.'-church' : $slug;
    }

    /**
     * Adds "-ending" to a slug, shortening the slug first so the result still fits the length limit.
     */
    private function withEnding(string $slug, string $ending): string
    {
        $room = config('onboarding.slug_max_length') - strlen($ending) - 1;

        return rtrim(Str::limit($slug, $room, ''), '-').'-'.$ending;
    }
}
