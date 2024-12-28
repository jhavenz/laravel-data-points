<?php

namespace DataPoints\LaravelDataPoints\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DataPoints\LaravelDataPoints\LaravelDataPoints
 */
class LaravelDataPoints extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DataPoints\LaravelDataPoints\LaravelDataPoints::class;
    }
}
