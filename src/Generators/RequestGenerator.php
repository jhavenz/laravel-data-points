<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\GeneratedArtifact;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class RequestGenerator implements Generator
{
    private const array TYPE_RULES = [
        'string' => ['required', 'string', 'max:255'],
        'text' => ['required', 'string'],
        'integer' => ['required', 'integer'],
        'bigInteger' => ['required', 'integer'],
        'float' => ['required', 'numeric'],
        'decimal' => ['required', 'numeric'],
        'boolean' => ['required', 'boolean'],
        'date' => ['required', 'date'],
        'datetime' => ['required', 'date'],
        'timestamp' => ['required', 'date'],
        'time' => ['required', 'date_format:H:i:s'],
        'year' => ['required', 'date_format:Y'],
        'email' => ['required', 'email'],
        'url' => ['required', 'url'],
        'password' => ['required', 'string', 'min:8'],
        'uuid' => ['required', 'uuid'],
        'ip' => ['required', 'ip'],
        'json' => ['required', 'json'],
    ];

    public string $type {
        get => 'request';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection
    {
        $result = collect();
        foreach ($dataPoints as $dataPoint) {
            $result[] = $this->generateStoreRequest($dataPoint, $options);
            $result[] = $this->generateUpdateRequest($dataPoint, $options);
        }

        return $result;
    }

    private function generateStoreRequest(DataPoint $dataPoint, TemplateOptions $options): GeneratedArtifact
    {
        $className = "Store{$dataPoint->name}Request";
        $this->generateRequest($className, $dataPoint, $options, true);
    }

    private function generateUpdateRequest(DataPoint $dataPoint, TemplateOptions $options): GeneratedArtifact
    {
        $className = "Update{$dataPoint->name}Request";
        $this->generateRequest($className, $dataPoint, $options, false);
    }

    private function generateRequest(string $className, DataPoint $dataPoint, TemplateOptions $options, bool $isStore): GeneratedArtifact
    {
        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\\Http\\Requests');
        $namespace->addUse('Illuminate\\Foundation\\Http\\FormRequest');
        $namespace->addUse('Illuminate\\Validation\\Rule');

        $class = $namespace->addClass($className)
            ->setExtends('Illuminate\\Foundation\\Http\\FormRequest');

        $this->addAuthorizeMethod($class);
        $this->addRulesMethod($class, $dataPoint, $isStore);

        return new GeneratedArtifact(
            $this->getFilePath($className, $options),
            (string) $file
        );
    }

    private function getFilePath(string $className, TemplateOptions $options): string
    {
        $namespace = str_replace('\\', '/', 'App/Http/Requests');
        $basePath = $options->outputPath ?? base_path();
        return $basePath . '/' . $namespace . '/' . $className . '.php';
    }

    private function addAuthorizeMethod(ClassType $class): void
    {
        $method = $class->addMethod('authorize')
            ->setPublic()
            ->setReturnType('bool')
            ->addComment('Determine if the user is authorized to make this request.')
            ->addComment('@return bool');

        $method->setBody('return true;');
    }

    private function addRulesMethod(ClassType $class, DataPoint $dataPoint, bool $isStore): void
    {
        $method = $class->addMethod('rules')
            ->setPublic()
            ->setReturnType('array')
            ->addComment('Get the validation rules that apply to the request.')
            ->addComment('@return array<string, mixed>');

        $rules = $this->getRules($dataPoint, $isStore);

        $method->setBody(
            "return [\n" .
            implode("\n", array_map(function($key, $value) {
                return "    '{$key}' => [" . implode(', ', array_map(fn($rule) => "'$rule'", $value)) . "],";
            }, array_keys($rules), $rules)) .
            "\n];"
        );
    }

    private function getFieldRules(Field $field, bool $isStore): array
    {
        $rules = self::TYPE_RULES[$field->type] ?? ['required'];

        // Make fields optional for updates
        if (!$isStore) {
            $rules = array_map(function($rule) {
                if ($rule === 'required') {
                    return 'sometimes';
                }
                if (str_starts_with($rule, 'unique:')) {
                    $parts = explode(',', $rule);
                    return $parts[0] . ',' . $parts[1] . ',' . '$this->route(\'' . Str::singular($parts[1]) . '\')';
                }
                return $rule;
            }, $rules);
        }

        // Add any custom rules from field options
        if (isset($field->options['rules'])) {
            $rules = array_merge($rules, (array) $field->options['rules']);
        }

        // Add nested validation rules for JSON/array fields
        if (isset($field->options['nested_rules'])) {
            foreach ($field->options['nested_rules'] as $key => $nestedRules) {
                $rules["{$field->name}.{$key}"] = $nestedRules;
            }
        }

        return array_values(array_unique($rules));
    }

    private function getRelationshipRules(Relationship $relationship, bool $isStore): array
    {
        $rules = [];
        $key = match ($relationship->type) {
            RelationType::BELONGS_TO => $relationship->options->foreignKey,
            RelationType::BELONGS_TO_MANY => Str::plural(Str::snake($relationship->related)),
            default => null,
        };

        if ($key === null) {
            return [];
        }

        if (isset($relationship->options->rules)) {
            $baseRules = $relationship->options->rules;
            if (!$isStore) {
                $baseRules = array_map(function($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                }, $baseRules);
            }
            $rules[$key] = $baseRules;
        }

        if (isset($relationship->options->itemRules)) {
            $rules["{$key}.*"] = $relationship->options->itemRules;
        }

        return $rules;
    }

    private function getRules(DataPoint $dataPoint, bool $isStore): array
    {
        $rules = [];

        // Field rules
        foreach ($dataPoint->fields as $field) {
            $fieldRules = $this->getFieldRules($field, $isStore);
            $rules[$field->name] = $fieldRules;

            // Add any nested rules
            if (isset($field->options['nested_rules'])) {
                foreach ($field->options['nested_rules'] as $key => $nestedRules) {
                    $rules["{$field->name}.{$key}"] = $nestedRules;
                }
            }
        }

        // Relationship rules
        foreach ($dataPoint->relationships as $relationship) {
            $relationshipRules = $this->getRelationshipRules($relationship, $isStore);
            $rules = array_merge($rules, $relationshipRules);
        }

        return $rules;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
