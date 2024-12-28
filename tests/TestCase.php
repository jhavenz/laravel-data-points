<?php

namespace DataPoints\LaravelDataPoints\Tests;

use DataPoints\LaravelDataPoints\LaravelDataPointsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataPointsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-data-points_table.php.stub';
        $migration->up();
        */
    }
}
