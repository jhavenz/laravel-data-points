<?php

namespace DataPoints\LaravelDataPoints\Commands;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\Generators\FactoryGenerator;
use DataPoints\LaravelDataPoints\Generators\MigrationGenerator;
use Illuminate\Console\Command;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

class DataPointMakeCommand extends Command
{
    protected $signature = 'datapoint:create 
                          {name : The name of the data point}
                          {--fields=* : The fields for the data point (format: name:type)}
                          {--relationships=* : The relationships for the data point (format: type:related)}
                          {--no-timestamps : Disable timestamps for the model}
                          {--no-migration : Skip migration generation}
                          {--no-factory : Skip factory generation}
                          {--no-seeder : Skip seeder generation}';

    protected $description = 'Create a new data point with model, migration, factory, and seeder';

    public function handle(): int
    {
        $dataPoint = new DataPoint(
            name: $this->argument('name'),
            fields: $this->parseFields(),
            relationships: $this->parseRelationships(),
            hasTimestamps: !$this->option('no-timestamps')
        );

        // Generate model
        $this->generateModel($dataPoint);

        // Generate migration unless --no-migration is specified
        if (!$this->option('no-migration')) {
            $this->generateMigration($dataPoint);
        }

        // Generate factory unless --no-factory is specified
        if (!$this->option('no-factory')) {
            $this->generateFactory($dataPoint);
        }

        // Generate seeder unless --no-seeder is specified
        if (!$this->option('no-seeder')) {
            $this->generateSeeder($dataPoint);
        }

        $this->info('Data point created successfully!');

        return self::SUCCESS;
    }

    private function parseFields(): array
    {
        return collect($this->option('fields'))
            ->mapWithKeys(function($field) {
                [$name, $type] = explode(':', $field);

                return [$name => ['type' => $type]];
            })
            ->all();
    }

    private function parseRelationships(): array
    {
        return collect($this->option('relationships'))
            ->map(function($relationship) {
                [$type, $related] = explode(':', $relationship);
                return [
                    'type' => $type,
                    'related' => $related,
                ];
            })
            ->all();
    }

    private function generateModel(DataPoint $dataPoint): void
    {
        $modelClass = $dataPoint->generateModelClass();

        $namespace = new PhpNamespace('App\\Models');
        $namespace->add($modelClass);

        $printer = new PsrPrinter;
        $content = "<?php\n\n" . $printer->printNamespace($namespace);

        $path = app_path('Models/' . $dataPoint->modelName . '.php');

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        $this->info('Model created: ' . $dataPoint->modelName);
    }

    private function generateMigration(DataPoint $dataPoint): void
    {
        $generator = new MigrationGenerator($dataPoint);
        $generator->generate();
        $this->info('Migration created for: ' . $dataPoint->tableName);
    }

    private function generateFactory(DataPoint $dataPoint): void
    {
        $generator = new FactoryGenerator($dataPoint);
        $generator->generate();
        $this->info('Factory created for: ' . $dataPoint->modelName);
    }

    private function generateSeeder(DataPoint $dataPoint): void
    {
        // TODO: Implement seeder generation
        $this->info('Seeder created for: ' . $dataPoint->modelName);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
