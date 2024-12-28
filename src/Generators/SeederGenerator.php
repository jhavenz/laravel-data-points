<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class SeederGenerator implements Generator
{
    public string $type {
        get => 'seeder';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): void
    {
        foreach ($dataPoints as $dataPoint) {
            $this->generateSeeder($dataPoint, $options);
        }

        $this->updateDatabaseSeeder($dataPoints, $options);
    }

    private function generateSeeder(DataPoint $dataPoint, TemplateOptions $options): void
    {
        $className = $dataPoint->name . 'Seeder';

        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('Database\\Seeders');
        $this->addImports($namespace, $dataPoint, $options);

        $class = $namespace->addClass($className)
            ->setExtends('Illuminate\\Database\\Seeder');

        $this->addRunMethod($class, $dataPoint);

        $printer = new PsrPrinter;
        $content = (string) $file;

        $path = database_path('seeders/' . $className . '.php');

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    private function addImports($namespace, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $namespace->addUse('Illuminate\\Database\\Seeder');
        $namespace->addUse($this->getNamespace($dataPoint, $options) . '\\' . $dataPoint->name);
    }

    private function addRunMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $method = $class->addMethod('run')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Run the database seeds.')
            ->addComment('@return void');

        $body = "\$count = 10; // Adjust this number based on your needs\n\n";
        $body .= "{$dataPoint->name}::factory(\$count)->create();\n";

        // If there are belongsTo relationships, we need to create those first
        $belongsToRelationships = $dataPoint->relationships
            ->filter(fn($relationship) => $relationship->type === RelationType::BELONGS_TO);

        if ($belongsToRelationships->isNotEmpty()) {
            $body = "// Create related models first\n";
            foreach ($belongsToRelationships as $relationship) {
                $body .= "\$related{$relationship->related} = {$relationship->related}::factory(\$count)->create();\n";
            }
            $body .= "\n// Create {$dataPoint->name} models with relationships\n";
            $body .= "{$dataPoint->name}::factory(\$count)\n";
            $body .= "    ->sequence(fn(\$sequence) => [\n";
            foreach ($belongsToRelationships as $relationship) {
                $foreignKey = $relationship->options->foreignKey ?? Str::snake($relationship->related) . '_id';
                $body .= "        '{$foreignKey}' => \$related{$relationship->related}[\$sequence->index]->id,\n";
            }
            $body .= "    ])\n";
            $body .= "    ->create();";
        }

        $method->setBody($body);
    }

    private function updateDatabaseSeeder(DataPointCollection $dataPoints, TemplateOptions $options): void
    {
        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('Database\\Seeders');
        $namespace->addUse('Illuminate\\Database\\Seeder');

        foreach ($dataPoints as $dataPoint) {
            $namespace->addUse('Database\\Seeders\\' . $dataPoint->name . 'Seeder');
        }

        $class = $namespace->addClass('DatabaseSeeder')
            ->setExtends('Illuminate\\Database\\Seeder');

        $method = $class->addMethod('run')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Run the database seeds.')
            ->addComment('@return void');

        $body = "// Truncate all tables\n";
        $body .= "\\DB::statement('SET FOREIGN_KEY_CHECKS=0;');\n\n";

        foreach ($dataPoints as $dataPoint) {
            $body .= "\\DB::table('" . Str::snake(Str::plural($dataPoint->name)) . "')->truncate();\n";
        }

        $body .= "\n\\DB::statement('SET FOREIGN_KEY_CHECKS=1;');\n\n";

        // Call seeders in order based on relationships
        $orderedDataPoints = $this->orderDataPointsByDependencies($dataPoints);
        foreach ($orderedDataPoints as $dataPoint) {
            $body .= "\$this->call({$dataPoint->name}Seeder::class);\n";
        }

        $method->setBody($body);

        $path = database_path('seeders/DatabaseSeeder.php');
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, (string) $file);
    }

    private function orderDataPointsByDependencies(DataPointCollection $dataPoints): array
    {
        $ordered = [];
        $unordered = $dataPoints->toArray();
        $dependencies = [];

        // Build dependency graph
        foreach ($dataPoints as $dataPoint) {
            $dependencies[$dataPoint->name] = [];
            foreach ($dataPoint->relationships as $relationship) {
                if ($relationship->type === RelationType::BELONGS_TO) {
                    $dependencies[$dataPoint->name][] = $relationship->related;
                }
            }
        }

        // Topological sort
        while (!empty($unordered)) {
            $progress = false;
            foreach ($unordered as $i => $dataPoint) {
                $canAdd = true;
                foreach ($dependencies[$dataPoint->name] as $dependency) {
                    if (!in_array($dependency, array_map(fn($dp) => $dp->name, $ordered))) {
                        $canAdd = false;
                        break;
                    }
                }
                if ($canAdd) {
                    $ordered[] = $dataPoint;
                    unset($unordered[$i]);
                    $progress = true;
                }
            }
            if (!$progress && !empty($unordered)) {
                // Circular dependency detected, add remaining in any order
                foreach ($unordered as $dataPoint) {
                    $ordered[] = $dataPoint;
                }
                break;
            }
        }

        return $ordered;
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
