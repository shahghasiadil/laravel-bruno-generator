<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

use ShahGhasiAdil\LaravelBrunoGenerator\Enums\OutputFormat;

final readonly class CollectionMetadata
{
    public function __construct(
        public string $name,
        public string $version,
        public string $type = 'collection',
        public OutputFormat $format = OutputFormat::BRU,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'name' => $this->name,
            'type' => $this->type,
        ];
    }
}
