<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

final readonly class BrunoRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $queryParams
     * @param array<string, string> $pathVariables
     * @param array<int, string> $tags
     */
    public function __construct(
        public string $name,
        public string $description,
        public int $sequence,
        public string $method,
        public string $url,
        public array $headers,
        public array $queryParams,
        public array $pathVariables,
        public ?RequestBody $body,
        public ?AuthBlock $auth,
        public ?string $group,
        public ?string $controller,
        public array $tags,
        public ?string $preRequestScript = null,
        public ?string $postResponseScript = null,
        public ?string $tests = null,
        public ?string $docs = null,
    ) {}

    public function hasHeaders(): bool
    {
        return !empty($this->headers);
    }

    public function hasQueryParams(): bool
    {
        return !empty($this->queryParams);
    }

    public function hasBody(): bool
    {
        return $this->body !== null && $this->body->hasContent();
    }

    public function hasAuth(): bool
    {
        return $this->auth !== null && !$this->auth->isNone();
    }
}
