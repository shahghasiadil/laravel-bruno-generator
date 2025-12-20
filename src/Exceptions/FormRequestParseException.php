<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Exceptions;

class FormRequestParseException extends BrunoGeneratorException
{
    public static function classNotFound(string $class): self
    {
        return new self("FormRequest class not found: {$class}");
    }

    public static function invalidFormRequest(string $class): self
    {
        return new self("Class is not a valid FormRequest: {$class}");
    }

    public static function rulesParseFailed(string $class, string $reason): self
    {
        return new self("Failed to parse rules from {$class}: {$reason}");
    }

    public static function maxNestingDepthExceeded(int $depth): self
    {
        return new self("Maximum nesting depth of {$depth} exceeded while parsing rules");
    }
}
