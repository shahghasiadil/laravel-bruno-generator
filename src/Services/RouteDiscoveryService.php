<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteDiscoveryInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;

final class RouteDiscoveryService implements RouteDiscoveryInterface
{
    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * Discover all routes from Laravel router.
     *
     * @return Collection<int, RouteInfo>
     */
    public function discover(): Collection
    {
        return collect($this->router->getRoutes())
            ->map(fn (Route $route) => $this->convertToRouteInfo($route))
            ->filter()
            ->values();
    }

    /**
     * Convert Laravel Route to RouteInfo DTO.
     */
    private function convertToRouteInfo(Route $route): ?RouteInfo
    {
        try {
            $action = $route->getActionName();
            [$controller, $controllerMethod] = $this->parseAction($action);

            return new RouteInfo(
                uri: $route->uri(),
                methods: $route->methods(),
                name: $route->getName(),
                action: $action,
                middleware: $this->getMiddleware($route),
                domain: $route->getDomain(),
                parameters: $this->extractParameters($route->uri()),
                controller: $controller,
                controllerMethod: $controllerMethod,
                isFallback: $route->isFallback,
            );
        } catch (\Throwable $e) {
            // Skip routes that cannot be analyzed
            return null;
        }
    }

    /**
     * Extract controller and method from route action.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function parseAction(string $action): array
    {
        // Handle closure actions
        if ($action === 'Closure' || str_contains($action, 'Closure')) {
            return [null, null];
        }

        // Handle controller@method format
        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);

            return [class_basename($controller), $method];
        }

        // Handle invokable controllers
        if (class_exists($action)) {
            return [class_basename($action), '__invoke'];
        }

        return [null, null];
    }

    /**
     * Get middleware for route.
     *
     * @return array<int, string>
     */
    private function getMiddleware(Route $route): array
    {
        $middleware = $route->gatherMiddleware();

        return array_map(function ($m) {
            // Extract middleware name from class or string
            if (is_string($m)) {
                // Remove parameters from middleware (e.g., "auth:sanctum" -> "auth:sanctum")
                return $m;
            }

            // Handle middleware objects
            if (is_object($m)) {
                return get_class($m);
            }

            return (string) $m;
        }, $middleware);
    }

    /**
     * Extract route parameters from URI pattern.
     *
     * @return array<string, string>
     */
    private function extractParameters(string $uri): array
    {
        $parameters = [];

        // Match {param} and {param?} patterns
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        if (! empty($matches[1])) {
            foreach ($matches[1] as $param) {
                // Remove optional marker (?)
                $cleanParam = str_replace('?', '', $param);
                $parameters[$cleanParam] = $param;
            }
        }

        return $parameters;
    }
}
