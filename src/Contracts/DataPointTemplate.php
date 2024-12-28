<?php

namespace DataPoints\LaravelDataPoints\Contracts;

use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;

interface DataPointTemplate
{
    public string $name {
        get;
    }

    public string $description {
        get;
    }

    public string $seederClass {
        get;
    }

    public array $generators {
        get;
    }

    public TemplateOptions $defaultOptions {
        get;
    }

    public function getDataPoints(TemplateOptions $options): DataPointCollection;
}
