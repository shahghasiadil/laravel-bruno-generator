<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator;

use Illuminate\Support\ServiceProvider;
use ShahGhasiAdil\LaravelBrunoGenerator\Commands\BrunoClearCommand;
use ShahGhasiAdil\LaravelBrunoGenerator\Commands\BrunoGenerateCommand;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\BrunoSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\CollectionOrganizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FileWriterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteDiscoveryInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteFilterInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\RouteNormalizerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\BrunoSerializerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\CollectionOrganizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FileWriterService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FormRequestParserService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteDiscoveryService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteFilterService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\RouteNormalizerService;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers\FormatSerializerFactory;

class BrunoGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/bruno-generator.php', 'bruno-generator');

        // Bind supporting services
        $this->app->singleton(FormRequestParserService::class);

        $this->app->singleton(FormatSerializerFactory::class, function ($app) {
            return new FormatSerializerFactory(config('bruno-generator', []));
        });

        // Bind contracts to implementations with proper config injection
        $this->app->singleton(RouteDiscoveryInterface::class, function ($app) {
            return new RouteDiscoveryService($app['router']);
        });

        $this->app->singleton(RouteFilterInterface::class, RouteFilterService::class);

        $this->app->singleton(RouteNormalizerInterface::class, function ($app) {
            return new RouteNormalizerService(
                $app->make(FormRequestParserService::class),
                config('bruno-generator', [])
            );
        });

        $this->app->singleton(CollectionOrganizerInterface::class, function ($app) {
            return new CollectionOrganizerService(config('bruno-generator', []));
        });

        $this->app->singleton(BrunoSerializerInterface::class, function ($app) {
            return new BrunoSerializerService(
                config('bruno-generator', []),
                $app->make(FormatSerializerFactory::class)
            );
        });

        $this->app->singleton(FileWriterInterface::class, FileWriterService::class);
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/bruno-generator.php' => config_path('bruno-generator.php'),
        ], 'bruno-generator-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                BrunoGenerateCommand::class,
                BrunoClearCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RouteDiscoveryInterface::class,
            RouteFilterInterface::class,
            RouteNormalizerInterface::class,
            CollectionOrganizerInterface::class,
            BrunoSerializerInterface::class,
            FileWriterInterface::class,
            FormRequestParserService::class,
            FormatSerializerFactory::class,
        ];
    }
}
