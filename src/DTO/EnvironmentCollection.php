<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

use Illuminate\Support\Collection;

final readonly class EnvironmentCollection
{
    /**
     * @param  Collection<string, EnvironmentConfig>  $environments
     */
    public function __construct(
        public Collection $environments,
    ) {}

    public static function empty(): self
    {
        return new self(collect());
    }

    public function hasEnvironments(): bool
    {
        return $this->environments->isNotEmpty();
    }
}
