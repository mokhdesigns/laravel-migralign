<?php

namespace MigrAlign;

use Illuminate\Support\ServiceProvider;
use MigrAlign\Commands\SyncMigrationsCommand;
use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\Scanning\MigrationScanner;

class MigrAlignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/migralign.php', 'migralign');

        $this->app->singleton(MigrationScanner::class, fn ($app) => new MigrationScanner(
            $app->make(\Illuminate\Filesystem\Filesystem::class)
        ));

        $this->app->singleton(RiskAnalyzer::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncMigrationsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/migralign.php' => config_path('migralign.php'),
            ], 'migralign-config');
        }
    }
}
