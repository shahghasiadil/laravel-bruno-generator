<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;

interface RouteDiscoveryInterface
{
    /**
     * Discover all routes from Laravel router.
     *
     * @return Collection<int, RouteInfo>
     */
    public function discover(): Collection;
}
