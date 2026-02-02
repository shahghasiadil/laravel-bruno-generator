<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

final readonly class RequestSettings
{
    public function __construct(
        public bool $encodeUrl = true,
        public int $timeout = 0,
        public bool $followRedirects = true,
        public int $maxRedirects = 5,
    ) {}

    /**
     * @return array<string, bool|int>
     */
    public function toArray(): array
    {
        return [
            'encodeUrl' => $this->encodeUrl,
            'timeout' => $this->timeout,
            'followRedirects' => $this->followRedirects,
            'maxRedirects' => $this->maxRedirects,
        ];
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            encodeUrl: $config['encode_url'] ?? true,
            timeout: $config['timeout'] ?? 0,
            followRedirects: $config['follow_redirects'] ?? true,
            maxRedirects: $config['max_redirects'] ?? 5,
        );
    }
}
