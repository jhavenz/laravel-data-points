<?php

namespace DataPoints\LaravelDataPoints\Contracts;

use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;

interface Generator
{
    public string $type {
        get;
    }

    /**
     * Generate the necessary files for the data points
     */
    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): void;
}
