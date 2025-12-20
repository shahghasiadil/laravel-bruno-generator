<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Contracts;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\GroupStrategy;

interface CollectionOrganizerInterface
{
    /**
     * Organize requests into hierarchical structure.
     *
     * @param  Collection<int, BrunoRequest>  $requests
     */
    public function organize(Collection $requests, GroupStrategy $strategy): CollectionStructure;
}
