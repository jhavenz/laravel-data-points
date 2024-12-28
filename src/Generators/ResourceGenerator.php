<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\GeneratedArtifact;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use Illuminate\Support\Collection;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class ResourceGenerator implements Generator
{
    public string $type {
        get => 'resource';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection
    {
        $results = collect();
        foreach ($dataPoints as $dataPoint) {
            $results[] = $this->generateResource($dataPoint, $options);
            $results[] = $this->generateResourceCollection($dataPoint, $options);
        }

        return $results;
    }

    private function generateResource(DataPoint $dataPoint, TemplateOptions $options): GeneratedArtifact
    {
        $className = $dataPoint->name . 'Resource';

        $file = new PhpFile;
        $file->setStrictTypes();

        $class = $file
            ->addNamespace('App\\Http\\Resources')
            ->addUse('Illuminate\\Http\\Request')
            ->addUse('Illuminate\\Http\\Resources\\Json\\JsonResource')
            ->addClass($className)
            ->setExtends('Illuminate\\Http\\Resources\\Json\\JsonResource');

        $this->addToArrayMethod($class, $dataPoint);

        return new GeneratedArtifact(
            $this->getFilePath($className, $options),
            (string) $file
        );
    }

    private function generateResourceCollection(DataPoint $dataPoint, TemplateOptions $options): GeneratedArtifact
    {
        $className = $dataPoint->name . 'Collection';

        $file = new PhpFile;
        $file->setStrictTypes();

        $class = $file
            ->addNamespace('App\\Http\\Resources')
            ->addUse('Illuminate\\Http\\Request')
            ->addUse('Illuminate\\Http\\Resources\\Json\\ResourceCollection')
            ->addClass($className)
            ->setExtends('Illuminate\\Http\\Resources\\Json\\ResourceCollection');

        $this->addCollectionToArrayMethod($class, $dataPoint);

        return new GeneratedArtifact(
            $this->getFilePath($className, $options),
            (string) $file
        );
    }

    private function getFilePath(string $className, TemplateOptions $options): string
    {
        $namespace = str_replace('\\', '/', 'App/Http/Resources');
        $basePath = $options->outputPath ?? base_path();
        return $basePath . '/' . $namespace . '/' . $className . '.php';
    }

    private function addToArrayMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $method = $class->addMethod('toArray')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->setReturnType('array')
            ->addComment('Transform the resource into an array.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@return array<string, mixed>');

        $body = "return [\n";
        $body .= "    'id' => \$this->id,\n";

        // Add all fields
        foreach ($dataPoint->fields as $field) {
            $body .= "    '{$field->name}' => \$this->{$field->name},\n";
        }

        // Add relationships
        foreach ($dataPoint->relationships as $relationship) {
            $resourceName = $relationship->related . 'Resource';
            $relationshipName = lcfirst($relationship->related);

            if ($this->isToManyRelationship($relationship->type)) {
                $body .= "    '{$relationshipName}' => {$resourceName}::collection(\$this->whenLoaded('{$relationshipName}')),\n";
            } else {
                $body .= "    '{$relationshipName}' => new {$resourceName}(\$this->whenLoaded('{$relationshipName}')),\n";
            }
        }

        if ($dataPoint->hasTimestamps) {
            $body .= "    'created_at' => \$this->created_at,\n";
            $body .= "    'updated_at' => \$this->updated_at,\n";
        }

        $body .= "];";

        $method->setBody($body);
    }

    private function addCollectionToArrayMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $method = $class->addMethod('toArray')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->setReturnType('array')
            ->addComment('Transform the resource collection into an array.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@return array<string, mixed>');

        $method->setBody(
            "return [\n" .
            "    'data' => \$this->collection,\n" .
            "    'meta' => [\n" .
            "        'total' => \$this->collection->count(),\n" .
            "    ],\n" .
            "];"
        );
    }

    private function isToManyRelationship(RelationType $type): bool
    {
        return match($type) {
            RelationType::HAS_MANY,
            RelationType::BELONGS_TO_MANY,
            RelationType::MORPH_MANY,
            RelationType::MORPH_TO_MANY,
            RelationType::MORPH_BY_MANY => true,
            default => false,
        };
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
