<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

final readonly class EnvironmentConfig
{
    /**
     * @param  array<int, EnvironmentVariable>  $variables
     */
    public function __construct(
        public string $name,
        public array $variables,
    ) {}

    public function hasVariables(): bool
    {
        return $this->variables !== [];
    }
}
