<?php

namespace Gzai\LaravelBoxAdapter\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Gzai\LaravelBoxAdapter\BoxAdapterServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            BoxAdapterServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            // 'database' => __DIR__ . '/../database/testing.sqlite',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // dump('Migrated ✅');

        DB::table('box_tokens')->insert([
            'access_token' => env('BOX_ACCESS_TOKEN'),
            'refresh_token' => env('BOX_REFRESH_TOKEN'),
            'expires_in' => (int) env('BOX_EXPIRES_IN'),
            'expires_at' => now()->addSeconds((int) env('BOX_EXPIRES_IN')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // dump('Inserted token record ✅');
    }
}