<?php

namespace DataPoints\LaravelDataPoints\Contracts;

use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\GeneratedArtifact;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use Illuminate\Support\Collection;

interface Generator
{
    public string $type {
        get;
    }

    /**
     * Generate the necessary files for the data points
     * 
     * @param DataPointCollection $dataPoints The data points to generate files for
     * @param TemplateOptions $options The options for generating files
     * @return Collection<GeneratedArtifact> The generated files
     */
    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection;
}
