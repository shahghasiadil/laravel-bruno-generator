<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteFilterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilterCriteria;

final class RouteFilterService implements RouteFilterInterface
{
    /**
     * Filter routes based on criteria.
     *
     * @param Collection<int, RouteInfo> $routes
     * @return Collection<int, RouteInfo>
     */
    public function filter(Collection $routes, FilterCriteria $criteria): Collection
    {
        return $routes
            ->filter(fn (RouteInfo $route) => $this->shouldIncludeRoute($route, $criteria))
            ->values();
    }

    /**
     * Determine if route should be included based on criteria.
     */
    private function shouldIncludeRoute(RouteInfo $route, FilterCriteria $criteria): bool
    {
        // Exclude fallback routes if configured
        if ($criteria->excludeFallback && $route->isFallback) {
            return false;
        }

        // Check domain filter
        if (!$this->matchesDomain($route, $criteria)) {
            return false;
        }

        // Check middleware filters
        if (!$this->matchesMiddleware($route, $criteria)) {
            return false;
        }

        // Check prefix filters
        if (!$this->matchesPrefix($route, $criteria)) {
            return false;
        }

        // Check name pattern filters
        if (!$this->matchesNamePattern($route, $criteria)) {
            return false;
        }

        return true;
    }

    /**
     * Check if route matches middleware criteria.
     */
    private function matchesMiddleware(RouteInfo $route, FilterCriteria $criteria): bool
    {
        // Auto-detect API routes
        if ($criteria->autoDetectApi) {
            $hasApiMiddleware = !empty(array_intersect($route->middleware, ['api']));
            if (!$hasApiMiddleware) {
                return false;
            }
        }

        // Check include middleware
        if (!empty($criteria->includeMiddleware)) {
            $hasIncludedMiddleware = !empty(array_intersect($route->middleware, $criteria->includeMiddleware));
            if (!$hasIncludedMiddleware) {
                return false;
            }
        }

        // Check exclude middleware
        if (!empty($criteria->excludeMiddleware)) {
            $hasExcludedMiddleware = !empty(array_intersect($route->middleware, $criteria->excludeMiddleware));
            if ($hasExcludedMiddleware) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if route matches prefix criteria.
     */
    private function matchesPrefix(RouteInfo $route, FilterCriteria $criteria): bool
    {
        // Check include prefixes
        if (!empty($criteria->includePrefixes)) {
            $matchesInclude = false;
            foreach ($criteria->includePrefixes as $prefix) {
                if (str_starts_with($route->uri, $prefix)) {
                    $matchesInclude = true;
                    break;
                }
            }
            if (!$matchesInclude) {
                return false;
            }
        }

        // Check exclude prefixes
        if (!empty($criteria->excludePrefixes)) {
            foreach ($criteria->excludePrefixes as $prefix) {
                if (str_starts_with($route->uri, $prefix)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if route name matches pattern criteria.
     */
    private function matchesNamePattern(RouteInfo $route, FilterCriteria $criteria): bool
    {
        // Skip if route has no name
        if ($route->name === null) {
            // If include patterns are specified, exclude unnamed routes
            return empty($criteria->includeNames);
        }

        // Check include name patterns
        if (!empty($criteria->includeNames)) {
            $matchesInclude = false;
            foreach ($criteria->includeNames as $pattern) {
                if ($this->matchesRegexPattern($route->name, $pattern)) {
                    $matchesInclude = true;
                    break;
                }
            }
            if (!$matchesInclude) {
                return false;
            }
        }

        // Check exclude name patterns
        if (!empty($criteria->excludeNames)) {
            foreach ($criteria->excludeNames as $pattern) {
                if ($this->matchesRegexPattern($route->name, $pattern)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if route matches domain criteria.
     */
    private function matchesDomain(RouteInfo $route, FilterCriteria $criteria): bool
    {
        // If no domain filter specified, include all
        if ($criteria->includeDomains === null) {
            return true;
        }

        // If route has no domain and filter is specified, exclude it
        if ($route->domain === null) {
            return false;
        }

        return in_array($route->domain, $criteria->includeDomains, true);
    }

    /**
     * Check if string matches regex pattern.
     */
    private function matchesRegexPattern(string $string, string $pattern): bool
    {
        // If pattern doesn't look like regex, treat as exact match
        if (!str_starts_with($pattern, '/')) {
            return $string === $pattern;
        }

        try {
            return (bool) preg_match($pattern, $string);
        } catch (\Throwable $e) {
            // Invalid regex, skip pattern
            return false;
        }
    }
}
