<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;

final readonly class RequestBody
{
    /**
     * @param array<string, mixed> $content
     */
    public function __construct(
        public BodyType $type,
        public array $content,
        public ?string $raw = null,
    ) {}

    public function hasContent(): bool
    {
        return !empty($this->content) || $this->raw !== null;
    }
}
