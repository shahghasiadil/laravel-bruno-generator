<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use ShahGhasiAdil\LaravelBrunoGenerator\Commands\Concerns\BuildsBrunoCollection;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\CollectionOrganizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteDiscoveryInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteFilterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\BrunoGeneratorException;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FileContent;
use ShahGhasiAdil\LaravelBrunoGenerator\ValueObjects\FilePath;

/**
 * Regenerates the Bruno collection in memory and compares it against what's
 * currently on disk, without writing anything. Exits non-zero when the
 * committed collection has drifted from what the current routes would
 * produce - intended for CI.
 */
final class BrunoCheckCommand extends Command
{
    use BuildsBrunoCollection;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bruno:check
                            {--format=bru : Output format (bru or yaml)}
                            {--output= : Output directory}
                            {--name= : Collection name}
                            {--api-only : Include only API routes}
                            {--prefix= : Filter by route prefix (comma-separated)}
                            {--exclude-prefix= : Exclude route prefix (comma-separated)}
                            {--middleware= : Include only routes with middleware (comma-separated)}
                            {--exclude-middleware= : Exclude routes with middleware (comma-separated)}
                            {--group-by= : Group routes by: prefix|controller|tag|none}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check whether the generated Bruno collection has drifted from what routes currently produce';

    public function __construct(
        private readonly RouteDiscoveryInterface $routeDiscovery,
        private readonly RouteFilterInterface $routeFilter,
        private readonly CollectionOrganizerInterface $collectionOrganizer,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $format = $this->option('format') ?? config('bruno-generator.output_format', 'bru');
            Config::set('bruno-generator.output_format', $format);

            $this->info("🔎 Checking Bruno collection for drift ({$format} format)...");
            $this->newLine();

            ['files' => $files] = $this->buildCollection($format);
            $outputPath = $this->getOutputPath();

            $added = collect();
            $changed = collect();

            foreach ($files as $file) {
                $absolutePath = $file['path']->absolutePath;

                if (! $this->filesystem->exists($absolutePath)) {
                    $added->push($file['path']->relativePath);

                    continue;
                }

                if ($this->filesystem->get($absolutePath) !== $file['content']->content) {
                    $changed->push($file['path']->relativePath);
                }
            }

            $orphaned = $this->findOrphanedFiles($outputPath, $files);

            $this->report($added, $changed, $orphaned);

            if ($added->isEmpty() && $changed->isEmpty() && $orphaned->isEmpty()) {
                $this->info('✅ No drift detected - the collection is up to date.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->error('❌ Drift detected. Run `php artisan bruno:generate --force` to update the collection.');

            return self::FAILURE;
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
     * Find files currently on disk under the output path that generation
     * would not (re)produce - i.e. left behind by routes that no longer
     * exist, or a prior run in a different format.
     *
     * @param  Collection<int, array{path: FilePath, content: FileContent}>  $files
     * @return Collection<int, string>
     */
    private function findOrphanedFiles(string $outputPath, Collection $files): Collection
    {
        if (! $this->filesystem->isDirectory($outputPath)) {
            return collect();
        }

        $generatedRelativePaths = $files->map(fn ($file) => $file['path']->relativePath)->all();

        return collect($this->filesystem->allFiles($outputPath))
            ->map(function ($existing) use ($outputPath) {
                $normalized = str_replace('\\', '/', $existing->getPathname());
                $normalizedBase = str_replace('\\', '/', rtrim($outputPath, '/'));

                return ltrim(substr($normalized, strlen($normalizedBase)), '/');
            })
            ->reject(fn (string $relativePath) => in_array($relativePath, $generatedRelativePaths, true))
            ->values();
    }

    /**
     * @param  Collection<int, string>  $added
     * @param  Collection<int, string>  $changed
     * @param  Collection<int, string>  $orphaned
     */
    private function report(Collection $added, Collection $changed, Collection $orphaned): void
    {
        $this->table(
            ['Status', 'Count'],
            [
                ['Would be added', $added->count()],
                ['Would change', $changed->count()],
                ['Orphaned on disk', $orphaned->count()],
            ],
        );

        if ($this->output->isVerbose()) {
            $this->listFiles('Added', $added);
            $this->listFiles('Changed', $changed);
            $this->listFiles('Orphaned', $orphaned);
        }
    }

    /**
     * @param  Collection<int, string>  $paths
     */
    private function listFiles(string $label, Collection $paths): void
    {
        if ($paths->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line("{$label}:");
        foreach ($paths as $path) {
            $this->line("  • {$path}");
        }
    }
}
