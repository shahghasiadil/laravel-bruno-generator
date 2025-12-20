<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\DTO;

use Illuminate\Support\Collection;

final readonly class CollectionStructure
{
    /**
     * @param Collection<int, FolderNode> $folders
     * @param Collection<int, BrunoRequest> $rootRequests
     */
    public function __construct(
        public CollectionMetadata $metadata,
        public Collection $folders,
        public Collection $rootRequests,
        public EnvironmentCollection $environments,
    ) {}

    public static function create(
        CollectionMetadata $metadata,
        EnvironmentCollection $environments,
    ): self {
        return new self(
            metadata: $metadata,
            folders: collect(),
            rootRequests: collect(),
            environments: $environments,
        );
    }

    public function hasFolders(): bool
    {
        return $this->folders->isNotEmpty();
    }

    public function hasRootRequests(): bool
    {
        return $this->rootRequests->isNotEmpty();
    }

    public function totalRequests(): int
    {
        $folderRequests = $this->folders->sum(function (FolderNode $folder) {
            return $this->countFolderRequests($folder);
        });

        return $this->rootRequests->count() + $folderRequests;
    }

    private function countFolderRequests(FolderNode $folder): int
    {
        $count = $folder->requests->count();

        foreach ($folder->subfolders as $subfolder) {
            $count += $this->countFolderRequests($subfolder);
        }

        return $count;
    }
}
