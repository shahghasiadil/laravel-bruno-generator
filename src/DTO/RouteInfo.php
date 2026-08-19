<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

final readonly class RouteInfo
{
    /**
     * @param  array<int, string>  $methods
     * @param  array<int, string>  $middleware
     * @param  array<string, string|null>  $parameters  Route parameter name => its where() regex constraint, or null when unconstrained.
     */
    public function __construct(
        public string $uri,
        public array $methods,
        public ?string $name,
        public string $action,
        public array $middleware,
        public ?string $domain,
        public array $parameters,
        public ?string $controller,
        public ?string $controllerMethod,
        public bool $isFallback = false,
    ) {}
}
