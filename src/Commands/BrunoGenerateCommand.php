<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use ShahGhasiAdil\LaravelBrunoGenerator\Commands\Concerns\BuildsBrunoCollection;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\CollectionOrganizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FileWriterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteDiscoveryInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteFilterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\BrunoGeneratorException;

final class BrunoGenerateCommand extends Command
{
    use BuildsBrunoCollection;

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

            ['structure' => $structure, 'files' => $files] = $this->buildCollection($format);
            $outputPath = $this->getOutputPath();

            // Write files (or dry-run)
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
