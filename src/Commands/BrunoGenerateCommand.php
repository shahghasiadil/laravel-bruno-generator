<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\CollectionOrganizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FileWriterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteDiscoveryInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteFilterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\GroupStrategy;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\BrunoGeneratorException;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\BrunoSerializerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\CollectionOrganizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FormRequestParserService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteNormalizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers\FormatSerializerFactory;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilterCriteria;

final class BrunoGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bruno:generate
                            {--format=bru : Output format (bru or yaml)}
                            {--output= : Output directory}
                            {--name= : Collection name}
                            {--api-only : Include only API routes}
                            {--prefix= : Filter by route prefix (comma-separated)}
                            {--exclude-prefix= : Exclude route prefix (comma-separated)}
                            {--middleware= : Include only routes with middleware (comma-separated)}
                            {--exclude-middleware= : Exclude routes with middleware (comma-separated)}
                            {--group-by= : Group routes by: prefix|controller|tag|none}
                            {--with-body-inference : Enable FormRequest body inference}
                            {--with-tests : Generate test blocks}
                            {--with-scripts : Generate script blocks}
                            {--with-docs : Include PHPDoc docs}
                            {--force : Overwrite existing collection}
                            {--dry-run : Preview without writing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Bruno API collection from Laravel routes';

    public function __construct(
        private readonly RouteDiscoveryInterface $routeDiscovery,
        private readonly RouteFilterInterface $routeFilter,
        private readonly CollectionOrganizerInterface $collectionOrganizer,
        private readonly FileWriterInterface $fileWriter,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Get format option and override config
            $format = $this->option('format') ?? config('bruno-generator.output_format', 'bru');

            // Override config temporarily for this command execution
            Config::set('bruno-generator.output_format', $format);

            $this->info("🚀 Generating Bruno collection in {$format} format...");
            $this->newLine();

            // Step 1: Discover routes
            $this->info('📍 Discovering routes...');
            $routes = $this->routeDiscovery->discover();
            $this->info("   Found {$routes->count()} routes");

            // Step 2: Filter routes
            $filterCriteria = $this->buildFilterCriteria();
            $this->info('🔍 Filtering routes...');
            $routes = $this->routeFilter->filter($routes, $filterCriteria);

            if ($routes->isEmpty()) {
                throw BrunoGeneratorException::noRoutesFound();
            }

            $this->info("   {$routes->count()} routes after filtering");

            // Step 3: Normalize routes
            // Re-create normalizer with updated config to respect format option for documentation
            $routeNormalizer = new RouteNormalizerService(
                app(FormRequestParserService::class),
                config('bruno-generator', [])
            );

            $this->info('⚙️  Normalizing routes...');
            $requests = $routeNormalizer->normalize($routes);
            $this->info("   Generated {$requests->count()} requests");

            // Step 4: Organize into collection
            $groupStrategy = $this->determineGroupStrategy();

            // Temporarily override collection name if --name option is provided
            $collectionOrganizer = $this->collectionOrganizer;
            if ($customName = $this->option('name')) {
                $config = config('bruno-generator', []);
                $config['collection_name'] = $customName;
                $collectionOrganizer = new CollectionOrganizerService($config);
            }

            $this->info('📁 Organizing collection...');
            $structure = $collectionOrganizer->organize($requests, $groupStrategy);
            $this->info("   Organized into {$structure->folders->count()} folders");

            // Step 5: Serialize to files
            // Re-create serializer with updated config to respect format option
            $updatedConfig = config('bruno-generator', []);
            $factory = new FormatSerializerFactory($updatedConfig);
            $brunoSerializer = new BrunoSerializerService(
                $updatedConfig,
                $factory
            );

            $outputPath = $this->getOutputPath();
            $this->info("✏️  Serializing to {$format} format...");
            $files = $brunoSerializer->serialize($structure, $outputPath);
            $this->info("   Generated {$files->count()} files");

            // Step 6: Write files (or dry-run)
            if ($this->option('dry-run')) {
                $this->displayDryRun($files, $structure);

                return self::SUCCESS;
            }

            $force = (bool) $this->option('force');
            $this->info('💾 Writing files...');
            $this->fileWriter->write($files, $force);

            $this->newLine();
            $this->info('✅ Successfully generated Bruno collection!');
            $this->info("📁 Output: {$outputPath}");
            $this->info("📊 Total requests: {$structure->totalRequests()}");
            $this->newLine();

            $this->displayNextSteps($outputPath);

            return self::SUCCESS;
        } catch (BrunoGeneratorException $e) {
            $this->newLine();
            $this->error("❌ {$e->getMessage()}");

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("❌ An unexpected error occurred: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Build filter criteria from options and config.
     */
    private function buildFilterCriteria(): FilterCriteria
    {
        $config = Config::get('bruno-generator.route_discovery', []);

        // Merge command options with config
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
        return preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
    }

    /**
     * Display dry-run information.
     *
     * @param  Collection  $files
     */
    private function displayDryRun($files, $structure): void
    {
        $this->newLine();
        $this->info('🔍 Dry-run mode - No files will be written');
        $this->newLine();

        $this->info('Files that would be generated:');
        $this->newLine();

        foreach ($files->take(10) as $file) {
            $this->line("  • {$file['path']->relativePath}");
        }

        if ($files->count() > 10) {
            $remaining = $files->count() - 10;
            $this->line("  ... and {$remaining} more files");
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $structure->totalRequests()],
                ['Total Files', $files->count()],
                ['Folders', $structure->folders->count()],
                ['Environments', $structure->environments->environments->count()],
            ],
        );
    }

    /**
     * Display next steps to user.
     */
    private function displayNextSteps(string $outputPath): void
    {
        $this->info('Next steps:');
        $this->line('  1. Open Bruno application');
        $this->line('  2. Click "Open Collection"');
        $this->line("  3. Navigate to: {$outputPath}");
        $this->line('  4. Start testing your API!');
        $this->newLine();
    }
}
