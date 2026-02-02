<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;

interface FormatSerializerInterface
{
    public function serializeRequest(BrunoRequest $request): string;

    /**
     * @param  array<string, string>  $variables
     */
    public function serializeEnvironment(string $name, array $variables): string;

    public function getFileExtension(): string;
}
