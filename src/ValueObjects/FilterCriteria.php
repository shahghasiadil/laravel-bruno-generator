<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects;

final readonly class FilterCriteria
{
    /**
     * @param  array<int, string>  $includeMiddleware
     * @param  array<int, string>  $excludeMiddleware
     * @param  array<int, string>  $includePrefixes
     * @param  array<int, string>  $excludePrefixes
     * @param  array<int, string>  $includeNames
     * @param  array<int, string>  $excludeNames
     * @param  array<int, string>|null  $includeDomains
     */
    public function __construct(
        public bool $autoDetectApi,
        public array $includeMiddleware,
        public array $excludeMiddleware,
        public array $includePrefixes,
        public array $excludePrefixes,
        public array $includeNames,
        public array $excludeNames,
        public ?array $includeDomains,
        public bool $excludeFallback,
    ) {}

    /**
     * Create from config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            autoDetectApi: (bool) ($config['auto_detect_api'] ?? true),
            includeMiddleware: (array) ($config['include_middleware'] ?? []),
            excludeMiddleware: (array) ($config['exclude_middleware'] ?? []),
            includePrefixes: (array) ($config['include_prefixes'] ?? []),
            excludePrefixes: (array) ($config['exclude_prefixes'] ?? []),
            includeNames: (array) ($config['include_names'] ?? []),
            excludeNames: (array) ($config['exclude_names'] ?? []),
            includeDomains: isset($config['include_domains']) ? (array) $config['include_domains'] : null,
            excludeFallback: (bool) ($config['exclude_fallback'] ?? true),
        );
    }
}
