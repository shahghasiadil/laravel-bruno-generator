<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilterCriteria;

interface RouteFilterInterface
{
    /**
     * Filter routes based on criteria.
     *
     * @param Collection<int, RouteInfo> $routes
     * @return Collection<int, RouteInfo>
     */
    public function filter(Collection $routes, FilterCriteria $criteria): Collection;
}
