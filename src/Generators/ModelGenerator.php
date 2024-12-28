<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class ModelGenerator implements Generator
{
    public string $type {
        get => 'model';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): void
    {
        foreach ($dataPoints as $dataPoint) {
            $this->generateModel($dataPoint, $options);
        }
    }

    private function generateModel(DataPoint $dataPoint, TemplateOptions $options): void
    {
        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace($this->getNamespace($dataPoint, $options));
        $namespace->addUse('Illuminate\Database\Eloquent\Model');

        // Add relationship imports based on what's used
        $relationTypes = $dataPoint->relationships->pluck('type')->unique();

        foreach ($relationTypes as $type) {
            $relationType = RelationType::from($type);

            $namespace->addUse($this->getRelationshipClass($relationType));
        }

        $class = $namespace->addClass($this->getClassName($dataPoint));
        $class->setExtends('Illuminate\Database\Eloquent\Model');

        $this->addTraitsAndInterfaces($class, $dataPoint);
        $this->addProperties($class, $dataPoint);
        $this->addCustomAttributes($class, $dataPoint);
        $this->addRelationships($class, $dataPoint);

        // Save the file
        $path = $this->getFilePath($dataPoint, $options);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, (string) $file);
    }

    private function getRelationshipClass(RelationType $type): string
    {
        return match($type) {
            RelationType::HAS_ONE => 'Illuminate\Database\Eloquent\Relations\HasOne',
            RelationType::HAS_MANY => 'Illuminate\Database\Eloquent\Relations\HasMany',
            RelationType::BELONGS_TO => 'Illuminate\Database\Eloquent\Relations\BelongsTo',
            RelationType::BELONGS_TO_MANY => 'Illuminate\Database\Eloquent\Relations\BelongsToMany',
            RelationType::MORPH_ONE => 'Illuminate\Database\Eloquent\Relations\MorphOne',
            RelationType::MORPH_MANY => 'Illuminate\Database\Eloquent\Relations\MorphMany',
            RelationType::MORPH_TO => 'Illuminate\Database\Eloquent\Relations\MorphTo',
            RelationType::MORPH_TO_MANY, RelationType::MORPH_BY_MANY => 'Illuminate\Database\Eloquent\Relations\MorphToMany',
        };
    }

    private function getNamespace(DataPoint $dataPoint, TemplateOptions $options): string
    {
        return $options->namespace ?? 'App\\Models';
    }

    private function getClassName(DataPoint $dataPoint): string
    {
        return class_basename($dataPoint->name);
    }

    private function getFilePath(DataPoint $dataPoint, TemplateOptions $options): string
    {
        $namespace = str_replace('\\', '/', $this->getNamespace($dataPoint, $options));
        return base_path($namespace . '/' . $this->getClassName($dataPoint) . '.php');
    }

    private function addTraitsAndInterfaces(ClassType $class, DataPoint $dataPoint): void
    {
        if (isset($dataPoint->options['traits'])) {
            foreach ($dataPoint->options['traits'] as $trait) {
                $class->addTrait($trait);
            }
        }

        if (isset($dataPoint->options['interfaces'])) {
            foreach ($dataPoint->options['interfaces'] as $interface) {
                $class->addImplement($interface);
            }
        }
    }

    private function addProperties(ClassType $class, DataPoint $dataPoint): void
    {
        if ($dataPoint->hasTimestamps) {
            $class->addProperty('timestamps', true)
                ->setPublic()
                ->setType('bool');
        }

        // Add fillable properties
        $fillable = $class->addProperty('fillable', [])
            ->setPublic()
            ->setType('array');

        foreach ($dataPoint->fields as $field) {
            $fillable->setValue([...$fillable->getValue(), $field->name]);

            // Add property with type hint and docblock
            $class->addProperty($field->name)
                ->setPublic()
                ->setType($this->getPhpType($field->type))
                ->addComment('@var ' . $field->type);
        }
    }

    private function addCustomAttributes(ClassType $class, DataPoint $dataPoint): void
    {
        $casts = [];
        $hidden = [];
        $appends = [];
        $attributes = [];

        foreach ($dataPoint->fields as $field) {
            if (isset($field->options['cast'])) {
                $casts[$field->name] = $field->options['cast'];
            }

            if (isset($field->options['hidden']) && $field->options['hidden']) {
                $hidden[] = $field->name;
            }

            if (isset($field->options['appends']) && $field->options['appends']) {
                $appends[] = $field->name;
                $this->addGetterAttribute($class, $field);
            }

            if (isset($field->options['default'])) {
                $attributes[$field->name] = $field->options['default'];
            }
        }

        if (!empty($casts)) {
            $class->addProperty('casts')
                ->setProtected()
                ->setValue($casts)
                ->addComment('@var array<string, string>');
        }

        if (!empty($hidden)) {
            $class->addProperty('hidden')
                ->setProtected()
                ->setValue($hidden)
                ->addComment('@var array<int, string>');
        }

        if (!empty($appends)) {
            $class->addProperty('appends')
                ->setProtected()
                ->setValue($appends)
                ->addComment('@var array<int, string>');
        }

        if (!empty($attributes)) {
            $class->addProperty('attributes')
                ->setProtected()
                ->setValue($attributes)
                ->addComment('@var array<string, mixed>');
        }
    }

    private function addGetterAttribute(ClassType $class, Field $field): void
    {
        $method = $class->addMethod('get' . Str::studly($field->name) . 'Attribute')
            ->setPublic()
            ->setReturnType($field->type === 'string' ? 'string' : 'mixed')
            ->addComment("Get the {$field->name} attribute.")
            ->addComment('@return ' . ($field->type === 'string' ? 'string' : 'mixed'));

        $method->setBody(
            match ($field->type) {
                'string' => "return Str::slug(\$this->attributes['{$field->name}']);",
                default => "return \$this->attributes['{$field->name}'];"
            }
        );
    }

    private function addRelationships(ClassType $class, DataPoint $dataPoint): void
    {
        foreach ($dataPoint->relationships as $relationship) {
            $this->addRelationshipMethod($class, $relationship);
        }
    }

    private function addRelationshipMethod(ClassType $class, Relationship $relationship): void
    {
        $method = $class->addMethod(Str::camel($relationship->related))
            ->setPublic()
            ->setReturnType($this->getReturnTypeForRelationship($relationship));

        $methodBody = match ($relationship->type) {
            RelationType::HAS_ONE => "\$this->hasOne({$relationship->related}::class",
            RelationType::HAS_MANY => "\$this->hasMany({$relationship->related}::class",
            RelationType::BELONGS_TO => "\$this->belongsTo({$relationship->related}::class",
            RelationType::BELONGS_TO_MANY => "\$this->belongsToMany({$relationship->related}::class",
            RelationType::MORPH_TO => "\$this->morphTo()",
            RelationType::MORPH_ONE => "\$this->morphOne({$relationship->related}::class",
            RelationType::MORPH_MANY => "\$this->morphMany({$relationship->related}::class",
            RelationType::MORPH_TO_MANY => "\$this->morphToMany({$relationship->related}::class",
            default => throw new \InvalidArgumentException("Unsupported relationship type: {$relationship->type->value}"),
        };

        if ($relationship->type !== RelationType::MORPH_TO) {
            $methodBody .= $this->getRelationshipOptions($relationship);
        }

        $method->setBody("return {$methodBody};");
    }

    private function getReturnTypeForRelationship(Relationship $relationship): string
    {
        return match ($relationship->type) {
            RelationType::HAS_ONE,
            RelationType::BELONGS_TO,
            RelationType::MORPH_ONE => 'Illuminate\\Database\\Eloquent\\Relations\\HasOne',
            RelationType::HAS_MANY,
            RelationType::MORPH_MANY => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            RelationType::BELONGS_TO_MANY,
            RelationType::MORPH_TO_MANY => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany',
            RelationType::MORPH_TO => 'Illuminate\\Database\\Eloquent\\Relations\\MorphTo',
            default => 'mixed',
        };
    }

    private function getRelationshipOptions(Relationship $relationship): string
    {
        $options = [];

        if ($relationship->options->foreignKey) {
            $options[] = "'{$relationship->options->foreignKey}'";
        }

        if ($relationship->options->localKey) {
            $options[] = "'{$relationship->options->localKey}'";
        }

        if ($relationship->options->table) {
            $options[] = "'{$relationship->options->table}'";
        }

        if ($relationship->type === RelationType::MORPH_TO) {
            if ($relationship->options->morphName) {
                $options[] = "'{$relationship->options->morphName}'";
            }
            if ($relationship->options->morphType) {
                $options[] = "'{$relationship->options->morphType}'";
            }
            if ($relationship->options->morphId) {
                $options[] = "'{$relationship->options->morphId}'";
            }
        }

        return empty($options) ? ')' : ', ' . implode(', ', $options) . ')';
    }

    private function getPhpType(string $type): string
    {
        return match($type) {
            'string', 'text' => 'string',
            'integer', 'bigInteger', 'unsignedBigInteger' => 'int',
            'boolean' => 'bool',
            'timestamp' => '\Carbon\Carbon',
            default => 'mixed'
        };
    }
}
