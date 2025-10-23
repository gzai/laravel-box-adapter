<?php

namespace Gzai\LaravelBoxAdapter;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Gzai\LaravelBoxAdapter\Filesystem\BoxAdapter;
use Gzai\LaravelBoxAdapter\Services\BoxAdapterService;

class BoxAdapterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/box.php', 'box');
    }

    public function boot(): void
    {
        $disks = Config::get('filesystems.disks', []);
        $boxDisk = require __DIR__ . '/../config/boxDisk.php';

        if (!array_key_exists('box', $disks)) {
            $disks['box'] = $boxDisk;
            Config::set('filesystems.disks', $disks);
        }

        $this->app->singleton('box', function ($app) {
            return new BoxAdapterService();
        });

        Storage::extend('box', function ($app, $config) {
            $adapter = new BoxAdapter($config);
            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter, $config);
        });

        Storage::disk('box')->buildTemporaryUrlsUsing(function ($path, $expiration, $options) {
            $boxService = new BoxAdapterService();
            $pathInfo = $boxService->pathInfo($path);
            $file = $boxService->getFileIdByName($pathInfo['last'], $pathInfo['parentId']);

            $fileId = $file['success'] ? $file['data']['id'] : 0;

            $timeNow = now();
            $expires = $timeNow->diffInSeconds($expiration);

            $link = $boxService->createTemporaryLink($fileId, $expires);

            return $link['success'] ? $link['data']['shared_link']['url'] : null;
        });

        if (class_exists(\Filament\Forms\Components\FileUpload::class)) {
            \Gzai\LaravelBoxAdapter\Filament\BoxUploadMacro::register();
        }

        $this->publishes([
            __DIR__.'/../config/box.php' => config_path('box.php'),
        ], 'laravel-box-adapter-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-box-adapter-migrations');

        if (config('box.routes_enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}