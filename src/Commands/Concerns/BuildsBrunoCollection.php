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
        $customName = $this->option('name');
        if (is_string($customName) && $customName !== '') {
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

        if ((bool) $this->option('api-only')) {
            $config['auto_detect_api'] = true;
        }

        $prefix = $this->option('prefix');
        if (is_string($prefix) && $prefix !== '') {
            $config['include_prefixes'] = explode(',', $prefix);
        }

        $excludePrefix = $this->option('exclude-prefix');
        if (is_string($excludePrefix) && $excludePrefix !== '') {
            $config['exclude_prefixes'] = array_merge(
                $config['exclude_prefixes'] ?? [],
                explode(',', $excludePrefix),
            );
        }

        $middleware = $this->option('middleware');
        if (is_string($middleware) && $middleware !== '') {
            $config['include_middleware'] = explode(',', $middleware);
        }

        $excludeMiddleware = $this->option('exclude-middleware');
        if (is_string($excludeMiddleware) && $excludeMiddleware !== '') {
            $config['exclude_middleware'] = array_merge(
                $config['exclude_middleware'] ?? [],
                explode(',', $excludeMiddleware),
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
