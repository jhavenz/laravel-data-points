<?php

namespace DataPoints\LaravelDataPoints;

use DataPoints\LaravelDataPoints\Commands\DataPointMakeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelDataPointsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-data-points')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_data_points_table')
            ->hasCommand(DataPointMakeCommand::class);
    }
}
