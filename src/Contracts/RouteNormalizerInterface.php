<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RouteInfo;

interface RouteNormalizerInterface
{
    /**
     * Normalize routes to Bruno request format.
     *
     * @param  Collection<int, RouteInfo>  $routes
     * @return Collection<int, BrunoRequest>
     */
    public function normalize(Collection $routes): Collection;
}
