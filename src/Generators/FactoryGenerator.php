<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

readonly class FactoryGenerator
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

    public function __construct(
        private DataPoint $dataPoint
    ) {}

    public function generate(): void
    {
        $className = $this->dataPoint->modelName . 'Factory';

        $namespace = new PhpNamespace('Database\\Factories');
        $namespace->addUse('Illuminate\\Database\\Eloquent\\Factories\\Factory');
        $namespace->addUse('Illuminate\\Support\\Str');
        $namespace->addUse('App\\Models\\' . $this->dataPoint->modelName);

        $class = $namespace->addClass($className)
            ->setExtends('Illuminate\\Database\\Eloquent\\Factories\\Factory');

        $this->addModelMethod($class);
        $this->addDefinitionMethod($class);

        $printer = new PsrPrinter;
        $content = "<?php\n\n" . $printer->printNamespace($namespace);

        $path = database_path('factories/' . $className . '.php');

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    private function addModelMethod(ClassType $class): void
    {
        $method = $class->addMethod('model')
            ->setPublic()
            ->setStatic()
            ->setReturnType('string')
            ->addComment('The name of the factory\'s corresponding model.');

        $method->setBody('return \\App\\Models\\' . $this->dataPoint->modelName . '::class;');
    }

    private function addDefinitionMethod(ClassType $class): void
    {
        $method = $class
            ->addMethod('definition')
            ->setPublic()
            ->setReturnType('array')
            ->addComment('Define the model\'s default state.')
            ->addComment('@return array<string, mixed>');

        $definitions = [
            ...$this->getFieldDefinitions(),
            ...$this->getRelationshipDefinitions()
        ];

        $method->setBody('return [' . "\n    " . implode(",\n    ", $definitions) . "\n];");
    }

    private function getFieldDefinitions(): array
    {
        return collect($this->dataPoint)
            ->map(fn($field, $name) => $this->getFakerDefinition($name, $field['type']))
            ->filter()
            ->all();
    }

    private function getRelationshipDefinitions(): array
    {
        return collect($this->dataPoint)
            ->filter(fn($relationship) => $relationship['type'] === 'belongsTo')
            ->map(function($relationship) {
                $relatedModel = 'App\\Models\\' . Str::studly($relationship['related']);
                $foreignKey = Str::snake($relationship['related']) . '_id';
                return "'$foreignKey' => \\$relatedModel::factory()";
            })
            ->all();
    }

    private function getFakerDefinition(string $name, string $type): ?string
    {
        $faker = self::TYPE_MAP[$type] ?? null;

        return $faker ? "'$name' => $faker" : null;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
