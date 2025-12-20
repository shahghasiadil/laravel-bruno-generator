<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

final readonly class CollectionMetadata
{
    public function __construct(
        public string $name,
        public string $version,
        public string $type = 'collection',
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
