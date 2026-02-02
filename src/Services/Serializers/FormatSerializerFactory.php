<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers;

use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FormatSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\OutputFormat;

final class FormatSerializerFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function make(OutputFormat $format): FormatSerializerInterface
    {
        return match ($format) {
            OutputFormat::BRU => new BruFormatSerializer(),
            OutputFormat::YAML => new YamlFormatSerializer($this->config),
        };
    }
}
