<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Commands\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\CollectionStructure;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\GroupStrategy;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\BrunoGeneratorException;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\BrunoSerializerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\CollectionOrganizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FormRequestParserService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteNormalizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers\FormatSerializerFactory;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilterCriteria;

/**
 * Shared route-discovery-to-file-map pipeline used by both bruno:generate
 * and bruno:check. Requires the consuming command to constructor-inject
 * routeDiscovery, routeFilter, and collectionOrganizer, and to declare the
 * same --format/--output/--name/--prefix/--exclude-prefix/--middleware/
 * --exclude-middleware/--api-only/--group-by options as bruno:generate.
 */
trait BuildsBrunoCollection
{
    /**
     * Run discovery through serialization, without writing anything to disk.
     *
     * @return array{structure: CollectionStructure, files: Collection<int, array{path: FilePath, content: FileContent}>}
     */
    private function buildCollection(string $format): array
    {
        $routes = $this->routeDiscovery->discover();

        $filterCriteria = $this->buildFilterCriteria();
        $routes = $this->routeFilter->filter($routes, $filterCriteria);

        if ($routes->isEmpty()) {
            throw BrunoGeneratorException::noRoutesFound();
        }

        // Re-create the normalizer with the format-aware config so
        // documentation truncation respects the --format option.
        $routeNormalizer = new RouteNormalizerService(
            app(FormRequestParserService::class),
            config('bruno-generator', [])
        );
        $requests = $routeNormalizer->normalize($routes);

        $groupStrategy = $this->determineGroupStrategy();

        $collectionOrganizer = $this->collectionOrganizer;
        if ($customName = $this->option('name')) {
            $config = config('bruno-generator', []);
            $config['collection_name'] = $customName;
            $collectionOrganizer = new CollectionOrganizerService($config);
        }

        $structure = $collectionOrganizer->organize($requests, $groupStrategy);

        $updatedConfig = config('bruno-generator', []);
        $factory = new FormatSerializerFactory($updatedConfig);
        $brunoSerializer = new BrunoSerializerService($updatedConfig, $factory);

        $files = $brunoSerializer->serialize($structure, $this->getOutputPath());

        return ['structure' => $structure, 'files' => $files];
    }

    /**
     * Build filter criteria from options and config.
     */
    private function buildFilterCriteria(): FilterCriteria
    {
        $config = Config::get('bruno-generator.route_discovery', []);

        if ($this->option('api-only')) {
            $config['auto_detect_api'] = true;
        }

        if ($this->option('prefix')) {
            $config['include_prefixes'] = explode(',', $this->option('prefix'));
        }

        if ($this->option('exclude-prefix')) {
            $config['exclude_prefixes'] = array_merge(
                $config['exclude_prefixes'] ?? [],
                explode(',', $this->option('exclude-prefix')),
            );
        }

        if ($this->option('middleware')) {
            $config['include_middleware'] = explode(',', $this->option('middleware'));
        }

        if ($this->option('exclude-middleware')) {
            $config['exclude_middleware'] = array_merge(
                $config['exclude_middleware'] ?? [],
                explode(',', $this->option('exclude-middleware')),
            );
        }

        return FilterCriteria::fromConfig($config);
    }

    /**
     * Determine group strategy from options.
     */
    private function determineGroupStrategy(): GroupStrategy
    {
        $strategy = $this->option('group-by') ?? Config::get('bruno-generator.organization.group_by', 'prefix');

        return GroupStrategy::from($strategy);
    }

    /**
     * Get output path from options or config.
     */
    private function getOutputPath(): string
    {
        $path = $this->option('output') ?? Config::get('bruno-generator.output_path');
        $collectionName = $this->option('name') ?? Config::get('bruno-generator.collection_name', 'Laravel API');

        $basePath = base_path($path);
        $sanitizedName = $this->sanitizeDirName($collectionName);

        return rtrim($basePath, '/').'/'.$sanitizedName;
    }

    /**
     * Sanitize collection name for directory.
     */
    private function sanitizeDirName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '-', $name) ?? $name;
    }
}
