<?php

namespace BeyondTheseWalls\FileManager;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FileManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'file-manager');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/file-manager'),
        ], 'file-manager-views');

        Livewire::component('file-manager', \BeyondTheseWalls\FileManager\Livewire\FileManager::class);
    }
}
