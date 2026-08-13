<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentVariable;

interface FormatSerializerInterface
{
    public function serializeRequest(BrunoRequest $request): string;

    /**
     * @param  array<int, EnvironmentVariable>  $variables
     */
    public function serializeEnvironment(string $name, array $variables): string;

    /**
     * Serialize the collection-level auth block that requests with
     * `auth: inherit` resolve against. Returns null when the format doesn't
     * (yet) support a collection-level auth file.
     */
    public function serializeCollectionAuth(AuthBlock $auth): ?string;

    public function getFileExtension(): string;
}
