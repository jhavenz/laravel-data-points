<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\GeneratedArtifact;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class FactoryGenerator implements Generator
{
    private const array TYPE_MAP = [
        'string' => 'fake()->sentence',
        'text' => 'fake()->paragraphs(3, true)',
        'integer' => 'fake()->randomNumber()',
        'bigInteger' => 'fake()->randomNumber()',
        'float' => 'fake()->randomFloat(2)',
        'decimal' => 'fake()->randomFloat(2)',
        'boolean' => 'fake()->boolean',
        'date' => 'fake()->date()',
        'datetime' => 'fake()->dateTime',
        'timestamp' => 'fake()->dateTime',
        'time' => 'fake()->time()',
        'year' => 'fake()->year',
        'email' => 'fake()->safeEmail',
        'url' => 'fake()->url',
        'password' => 'bcrypt(fake()->password)',
        'remember_token' => 'Str::random(10)',
        'uuid' => 'Str::uuid()',
        'ip' => 'fake()->ipv4',
        'json' => '[]',
    ];

    public string $type {
        get => 'factory';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection
    {
        $results = collect();
        foreach ($dataPoints as $dataPoint) {
            $results[] = $this->generateFactory($dataPoint, $options);
        }

        return $results;
    }

    private function generateFactory(DataPoint $dataPoint, TemplateOptions $options): GeneratedArtifact
    {
        $className = $dataPoint->name . 'Factory';

        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('Database\\Factories');
        $namespace->addUse('Illuminate\\Database\\Eloquent\\Factories\\Factory');
        $namespace->addUse('Illuminate\\Support\\Str');
        $namespace->addUse($this->getNamespace($dataPoint, $options) . '\\' . $dataPoint->name);

        $class = $namespace->addClass($className)
            ->setExtends('Illuminate\\Database\\Eloquent\\Factories\\Factory');

        $this->addModelMethod($class, $dataPoint, $options);
        $this->addDefinitionMethod($class, $dataPoint);

        return new GeneratedArtifact(
            $this->getFilePath($dataPoint, $options),
            (string) $file
        );
    }

    private function getFilePath(DataPoint $dataPoint, TemplateOptions $options): string
    {
        $namespace = str_replace('\\', '/', $this->getNamespace($dataPoint, $options));
        $basePath = $options->outputPath ?? base_path();
        return $basePath . '/' . $namespace . '/' . $dataPoint->name . 'Factory.php';
    }

    private function addModelMethod(ClassType $class, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $method = $class->addMethod('model')
            ->setPublic()
            ->setStatic()
            ->setReturnType('string')
            ->addComment('The name of the factory\'s corresponding model.');

        $method->setBody('return \\' . $this->getNamespace($dataPoint, $options) . '\\' . $dataPoint->name . '::class;');
    }

    private function addDefinitionMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $method = $class
            ->addMethod('definition')
            ->setPublic()
            ->setReturnType('array')
            ->addComment('Define the model\'s default state.')
            ->addComment('@return array<string, mixed>');

        $definitions = [
            ...$this->getFieldDefinitions($dataPoint),
            ...$this->getRelationshipDefinitions($dataPoint)
        ];

        $method->setBody('return [' . "\n    " . implode(",\n    ", $definitions) . "\n];");
    }

    private function getFieldDefinitions(DataPoint $dataPoint): array
    {
        return $dataPoint->fields
            ->map(fn(Field $field) => $this->getFakerDefinition($field->name, $field->type))
            ->filter()
            ->all();
    }

    private function getRelationshipDefinitions(DataPoint $dataPoint): array
    {
        return $dataPoint->relationships
            ->filter(fn($relationship) => $relationship->type === RelationType::BELONGS_TO)
            ->map(function($relationship) use ($dataPoint) {
                $relatedModel = $this->getNamespace($dataPoint) . '\\' . $relationship->related;
                $foreignKey = $relationship->options->foreignKey ?? Str::snake($relationship->related) . '_id';
                return "'$foreignKey' => \\$relatedModel::factory()";
            })
            ->all();
    }

    private function getFakerDefinition(string $name, string $type): ?string
    {
        $faker = self::TYPE_MAP[$type] ?? null;

        return $faker ? "'$name' => $faker" : null;
    }

    private function getNamespace(DataPoint $dataPoint, ?TemplateOptions $options = null): string
    {
        return $options?->namespace ?? 'App\\Models';
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
