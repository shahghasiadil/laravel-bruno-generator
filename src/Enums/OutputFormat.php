<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Enums;

enum OutputFormat: string
{
    case BRU = 'bru';
    case YAML = 'yaml';

    public function getExtension(): string
    {
        return match ($this) {
            self::BRU => '.bru',
            self::YAML => '.yaml',
        };
    }

    public static function fromString(string $format): self
    {
        return match (strtolower($format)) {
            'yaml', 'yml' => self::YAML,
            'bru' => self::BRU,
            default => self::BRU,
        };
    }
}
