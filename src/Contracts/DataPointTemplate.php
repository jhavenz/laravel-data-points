<?php

namespace DataPoints\LaravelDataPoints\Contracts;

use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;

interface DataPointTemplate
{
    public string $name { get; }

    public string $description { get; }

    public string $seederClass { get; }

    public DataPointCollection $dataPoints { get; }
}
