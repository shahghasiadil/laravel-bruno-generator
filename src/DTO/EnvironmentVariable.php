<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

final readonly class EnvironmentVariable
{
    public function __construct(
        public string $name,
        public string $value,
        public bool $secret = false,
        public ?string $description = null,
    ) {}
}
