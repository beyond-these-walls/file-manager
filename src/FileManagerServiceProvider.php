<?php

namespace BeyondTheseWalls\FileManager;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/file-manager.php', 'file-manager');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'file-manager');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'file-manager');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/file-manager'),
        ], 'file-manager-views');

        $this->publishes([
            __DIR__.'/../config/file-manager.php' => config_path('file-manager.php'),
        ], 'file-manager-config');

        Livewire::component('file-manager', \BeyondTheseWalls\FileManager\Livewire\FileManager::class);
    }
}
