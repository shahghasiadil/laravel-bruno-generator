<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

final class BrunoClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bruno:clear
                            {path? : Path to collection to clear}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear generated Bruno collection';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->getCollectionPath();

        if (!$this->filesystem->exists($path)) {
            $this->info("Collection does not exist at: {$path}");

            return self::SUCCESS;
        }

        // Confirm deletion
        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete the collection at {$path}?", false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            $this->filesystem->deleteDirectory($path);
            $this->info("✅ Successfully deleted collection at: {$path}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Failed to delete collection: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get collection path from argument or config.
     */
    private function getCollectionPath(): string
    {
        if ($this->argument('path')) {
            return base_path($this->argument('path'));
        }

        $outputPath = Config::get('bruno-generator.output_path');
        $collectionName = Config::get('bruno-generator.collection_name', 'Laravel API');

        $basePath = base_path($outputPath);
        $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $collectionName);

        return rtrim($basePath, '/') . '/' . $sanitizedName;
    }
}
