<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services;

use Illuminate\Support\Collection;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\CollectionOrganizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionMetadata;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentCollection;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentConfig;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\FolderNode;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\GroupStrategy;

final class CollectionOrganizerService implements CollectionOrganizerInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * Organize requests into hierarchical structure.
     *
     * @param Collection<int, BrunoRequest> $requests
     */
    public function organize(Collection $requests, GroupStrategy $strategy): CollectionStructure
    {
        // Sort requests first
        $sortedRequests = $this->sortRequests($requests);

        // Reassign sequence numbers after sorting
        $sortedRequests = $this->reassignSequences($sortedRequests);

        // Group requests based on strategy
        $folders = match ($strategy) {
            GroupStrategy::PREFIX => $this->groupByPrefix($sortedRequests),
            GroupStrategy::CONTROLLER => $this->groupByController($sortedRequests),
            GroupStrategy::TAG => $this->groupByTag($sortedRequests),
            GroupStrategy::NONE => collect(),
        };

        // Extract root requests
        // When strategy is NONE, all requests are root requests
        // Otherwise, only requests without a group are root requests
        $rootRequests = $strategy === GroupStrategy::NONE
            ? $sortedRequests
            : $sortedRequests->filter(fn (BrunoRequest $request) => $request->group === null);

        // Create metadata
        $metadata = new CollectionMetadata(
            name: $this->config['collection_name'] ?? 'Laravel API',
            version: $this->config['bruno_version'] ?? '1',
        );

        // Create environments
        $environments = $this->createEnvironments();

        return new CollectionStructure(
            metadata: $metadata,
            folders: $folders,
            rootRequests: $rootRequests,
            environments: $environments,
        );
    }

    /**
     * Sort requests based on configuration.
     *
     * @param Collection<int, BrunoRequest> $requests
     * @return Collection<int, BrunoRequest>
     */
    private function sortRequests(Collection $requests): Collection
    {
        $sortBy = $this->config['organization']['sort_by'] ?? 'uri';
        $direction = $this->config['organization']['sort_direction'] ?? 'asc';

        $sorted = match ($sortBy) {
            'uri' => $requests->sortBy('url'),
            'method' => $requests->sortBy('method'),
            'name' => $requests->sortBy('name'),
            default => $requests,
        };

        return $direction === 'desc' ? $sorted->reverse()->values() : $sorted->values();
    }

    /**
     * Reassign sequence numbers after sorting.
     *
     * @param Collection<int, BrunoRequest> $requests
     * @return Collection<int, BrunoRequest>
     */
    private function reassignSequences(Collection $requests): Collection
    {
        $increment = $this->config['organization']['sequence_increment'] ?? 1;
        $sequence = 0;

        return $requests->map(function (BrunoRequest $request) use (&$sequence, $increment) {
            $sequence += $increment;

            return new BrunoRequest(
                name: $request->name,
                description: $request->description,
                sequence: $sequence,
                method: $request->method,
                url: $request->url,
                headers: $request->headers,
                queryParams: $request->queryParams,
                pathVariables: $request->pathVariables,
                body: $request->body,
                auth: $request->auth,
                group: $request->group,
                controller: $request->controller,
                tags: $request->tags,
                preRequestScript: $request->preRequestScript,
                postResponseScript: $request->postResponseScript,
                tests: $request->tests,
                docs: $request->docs,
            );
        });
    }

    /**
     * Group requests by prefix.
     *
     * @param Collection<int, BrunoRequest> $requests
     * @return Collection<int, FolderNode>
     */
    private function groupByPrefix(Collection $requests): Collection
    {
        $grouped = $requests
            ->filter(fn (BrunoRequest $request) => $request->group !== null)
            ->groupBy('group');

        $folders = collect();

        foreach ($grouped as $group => $groupRequests) {
            $folder = $this->createFolderHierarchy((string) $group, $groupRequests);
            if ($folder !== null) {
                $folders->push($folder);
            }
        }

        return $folders;
    }

    /**
     * Create folder hierarchy from path.
     *
     * @param Collection<int, BrunoRequest> $requests
     */
    private function createFolderHierarchy(string $path, Collection $requests): ?FolderNode
    {
        $parts = explode('/', $path);

        if (empty($parts)) {
            return null;
        }

        // Create root folder
        $rootName = array_shift($parts);
        $root = FolderNode::create($rootName, $rootName);

        if (empty($parts)) {
            // No subfolders, add requests to root
            return new FolderNode(
                name: $root->name,
                path: $root->path,
                requests: $requests,
                subfolders: collect(),
            );
        }

        // Create nested folder structure
        $subfolders = $this->createSubfolders($parts, $root->path, $requests);

        return new FolderNode(
            name: $root->name,
            path: $root->path,
            requests: collect(),
            subfolders: $subfolders,
        );
    }

    /**
     * Create subfolders recursively.
     *
     * @param array<int, string> $parts
     * @param Collection<int, BrunoRequest> $requests
     * @return Collection<int, FolderNode>
     */
    private function createSubfolders(array $parts, string $parentPath, Collection $requests): Collection
    {
        if (empty($parts)) {
            return collect();
        }

        $name = array_shift($parts);
        $path = $parentPath . '/' . $name;

        if (empty($parts)) {
            // Leaf folder - add requests here
            return collect([
                new FolderNode(
                    name: $name,
                    path: $path,
                    requests: $requests,
                    subfolders: collect(),
                ),
            ]);
        }

        // Create nested subfolders
        $subfolders = $this->createSubfolders($parts, $path, $requests);

        return collect([
            new FolderNode(
                name: $name,
                path: $path,
                requests: collect(),
                subfolders: $subfolders,
            ),
        ]);
    }

    /**
     * Group requests by controller.
     *
     * @param Collection<int, BrunoRequest> $requests
     * @return Collection<int, FolderNode>
     */
    private function groupByController(Collection $requests): Collection
    {
        $grouped = $requests
            ->filter(fn (BrunoRequest $request) => $request->controller !== null)
            ->groupBy('controller');

        return $grouped->map(function (Collection $groupRequests, string $controller) {
            return new FolderNode(
                name: $controller,
                path: $controller,
                requests: $groupRequests,
                subfolders: collect(),
            );
        })->values();
    }

    /**
     * Group requests by tag.
     *
     * @param Collection<int, BrunoRequest> $requests
     * @return Collection<int, FolderNode>
     */
    private function groupByTag(Collection $requests): Collection
    {
        // Extract all unique tags
        $allTags = $requests
            ->flatMap(fn (BrunoRequest $request) => $request->tags)
            ->unique()
            ->values();

        // Group requests by first tag
        $folders = collect();

        foreach ($allTags as $tag) {
            $tagRequests = $requests->filter(function (BrunoRequest $request) use ($tag) {
                return !empty($request->tags) && $request->tags[0] === $tag;
            });

            if ($tagRequests->isNotEmpty()) {
                $folders->push(new FolderNode(
                    name: $tag,
                    path: $tag,
                    requests: $tagRequests,
                    subfolders: collect(),
                ));
            }
        }

        return $folders;
    }

    /**
     * Create environment collection from config.
     */
    private function createEnvironments(): EnvironmentCollection
    {
        $environments = collect($this->config['environments'] ?? [])
            ->map(function (array $variables, string $name) {
                return new EnvironmentConfig(
                    name: $name,
                    variables: $variables,
                );
            });

        return new EnvironmentCollection(environments: $environments);
    }
}
