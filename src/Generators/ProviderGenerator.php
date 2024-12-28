<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\GeneratedArtifact;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use Illuminate\Support\Collection;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class ProviderGenerator implements Generator
{
    public string $type {
        get => 'provider';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection
    {
        return collect([
            $this->generateAuthServiceProvider($dataPoints, $options),
        ]);
    }

    private function generateAuthServiceProvider(DataPointCollection $dataPoints, TemplateOptions $options): GeneratedArtifact
    {
        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\\Providers');
        $namespace->addUse('Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider');
        $namespace->addUse('Illuminate\\Support\\Facades\\Gate');

        foreach ($dataPoints as $dataPoint) {
            $namespace->addUse($this->getNamespace($dataPoint, $options).'\\'.$dataPoint->name);
            $namespace->addUse('App\\Policies\\'.$dataPoint->name.'Policy');
        }

        $class = $namespace
            ->addClass('AuthServiceProvider')
            ->setExtends('Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider');

        // Add policies property
        $policies = $class->addProperty('policies')
            ->setProtected()
            ->setType('array')
            ->setValue([]);

        $policiesValue = [];
        foreach ($dataPoints as $dataPoint) {
            $policiesValue[$dataPoint->name.'::class'] = $dataPoint->name.'Policy::class';
        }

        $policies->setValue($policiesValue);

        // Add boot method
        $boot = $class
            ->addMethod('boot')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Register any authentication / authorization services.')
            ->addComment('@return void');

        $boot->setBody(
            "\$this->registerPolicies();\n\n".
            "//\n"
        );

        $path = $options->outputPath ?? base_path();
        return new GeneratedArtifact(
            $path.'/app/Providers/AuthServiceProvider.php',
            (string) $file
        );
    }

    private function getNamespace(DataPoint $dataPoint, ?TemplateOptions $options = null): string
    {
        return $options?->namespace ?? 'App\\Models';
    }
}
