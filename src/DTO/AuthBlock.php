<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

use ShahGhasiAdil\LaravelBrunoGenerator\Enums\AuthType;

final readonly class AuthBlock
{
    /**
     * @param array<string, string> $config
     */
    public function __construct(
        public AuthType $type,
        public array $config,
    ) {}

    public function isNone(): bool
    {
        return $this->type === AuthType::NONE;
    }
}
