<?php

namespace DataPoints\LaravelDataPoints;

use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;

/**
 * @property-read string $name
 * @property-read Collection<int, Field> $fields
 * @property-read Collection<int, Relationship> $relationships
 * @property-read bool $timestamps
 * @property-read array $additionalTraits
 * @property-read array $additionalInterfaces
 */
class DataPoint
{
    public string $modelName {
        get => Str::studly(Str::singular($this->name));
    }

    public string $tableName {
        get => Str::snake(Str::plural($this->name));
    }

    public function __construct(
        private(set) string $name {
            get => $this->name;
        },
        private(set) Collection $fields = new Collection() {
            get => $this->fields;
        },
        private(set) Collection $relationships = new Collection() {
            get => $this->relationships;
        },
        private(set) readonly bool $hasTimestamps = true,
        private readonly array $additionalTraits = [],
        private readonly array $additionalInterfaces = [],
    ) {
        $this->fields = collect($fields)->map(fn($field, $name) =>
            $field instanceof Field
                ? $field
                : Field::from($name, $field['type'], $field['options'] ?? [])
        );

        $this->relationships = collect($relationships)->map(fn($rel) =>
            $rel instanceof Relationship
                ? $rel
                : Relationship::from($rel['type'], $rel['related'], $rel['options'] ?? [])
        );
    }

    public function with(
        ?string $name = null,
        ?array $fields = null,
        ?array $relationships = null,
        ?bool $hasTimestamps = null,
        ?array $additionalTraits = null,
        ?array $additionalInterfaces = null
    ): self {
        return new self(
            name: $name ?? $this->name,
            fields: $fields ?? $this->fields,
            relationships: $relationships ?? $this->relationships,
            hasTimestamps: $hasTimestamps ?? $this->hasTimestamps,
            additionalTraits: $additionalTraits ?? $this->additionalTraits,
            additionalInterfaces: $additionalInterfaces ?? $this->additionalInterfaces
        );
    }

    public function addField(string $name, string $type, array $options = []): self
    {
        return $this->with(fields: [
            ...$this->fields,
            Field::from($name, $type, $options),
        ]);
    }

    public function addRelationship(string $type, string $related, array $options = []): self
    {
        return $this->with(relationships: [
            ...$this->relationships,
            Relationship::from($type, $related, $options),
        ]);
    }

    public function uses(string $trait): self
    {
        return $this->with(additionalTraits: [...$this->additionalTraits, $trait]);
    }

    public function implements(string $interface): self
    {
        return $this->with(additionalInterfaces: [...$this->additionalInterfaces, $interface]);
    }

    public function generateModelClass(): ClassType
    {
        $class = new ClassType($this->modelName);
        $class->setExtends('Illuminate\\Database\\Eloquent\\Model');

        // Add default traits
        $class->addTrait('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');

        // Add additional traits and interfaces
        foreach ($this->additionalTraits as $trait) {
            $class->addTrait($trait);
        }

        foreach ($this->additionalInterfaces as $interface) {
            $class->addImplement($interface);
        }

        // Add table property if different from convention
        if ($this->tableName !== Str::snake(Str::pluralStudly($this->name))) {
            $class->addProperty('table', $this->tableName)
                ->setReadOnly()
                ->setType('string')
                ->addComment('The table associated with the model.');
        }

        // Add fillable property
        $class->addProperty('fillable', $this->fields->keys()->all())
            ->setType('array')
            ->setReadOnly()
            ->addComment('The attributes that are mass assignable.');

        // Add timestamps property if disabled
        if (!$this->hasTimestamps) {
            $class->addProperty('timestamps', false)
                ->setPublic()
                ->setType('bool');
        }

        // Add relationships
        foreach ($this->relationships as $relationship) {
            $this->addRelationshipMethod($class, $relationship);
        }

        return $class;
    }

    private function addRelationshipMethod(ClassType $class, Relationship $relationship): void
    {
        $methodName = Str::camel($relationship->related);
        $method = $class->addMethod($methodName);

        $relatedClass = 'App\\Models\\' . Str::studly($relationship->related);

        $method->setPublic()
            ->setReturnType('\\Illuminate\\Database\\Eloquent\\Relations\\' . ucfirst($relationship->type))
            ->setBody(sprintf(
                'return $this->%s(%s::class%s);',
                $relationship->type,
                $relatedClass,
                $this->getRelationshipParameters($relationship)
            ));
    }

    private function getRelationshipParameters(Relationship $relationship): string
    {
        $options = $relationship->options->toArray();

        if (empty($options)) {
            return '';
        }

        return ', ' . implode(', ', array_map(
            fn($value) => is_string($value) ? "'$value'" : $value,
            $options
        ));
    }

    public function withoutTimestamps(): self
    {
        return $this->with(hasTimestamps: false);
    }
}
