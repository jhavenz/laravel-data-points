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
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

class MigrationGenerator implements Generator
{
    public string $type {
        get => 'migration';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection
    {
        $results = collect();
        foreach ($dataPoints as $dataPoint) {
            $results[] = $this->generateMigration($dataPoint);
        }

        return $results;
    }

    private function generateMigration(DataPoint $dataPoint): GeneratedArtifact
    {
        $className = 'Create' . Str::studly($dataPoint->tableName) . 'Table';

        $namespace = new PhpNamespace('Database\\Migrations');
        $namespace->addUse('Illuminate\\Database\\Migrations\\Migration');
        $namespace->addUse('Illuminate\\Database\\Schema\\Blueprint');
        $namespace->addUse('Illuminate\\Support\\Facades\\Schema');

        $class = $namespace
            ->addClass($className)
            ->setExtends('Illuminate\\Database\\Migrations\\Migration');

        $this->addUpMethod($class, $dataPoint);
        $this->addDownMethod($class, $dataPoint);

        $printer = new PsrPrinter;
        $content = "<?php\n\n" . $printer->printNamespace($namespace);

        $filename = date('Y_m_d_His') . '_create_' .
            $dataPoint->tableName . '_table.php';

        return new GeneratedArtifact(
            database_path('migrations/' . $filename),
            $content
        );
    }

    private function addUpMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $method = $class
            ->addMethod('up')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Run the migrations.');

        $body = "Schema::create('{$dataPoint->tableName}', function (Blueprint \$table) {\n";
        $body .= "    \$table->id();\n";

        foreach ($dataPoint->fields as $field) {
            $body .= $this->generateFieldDefinition($field);
        }

        // Add foreign keys for relationships
        foreach ($dataPoint->relationships as $relationship) {
            if ($relationship->type === RelationType::BELONGS_TO) {
                $foreignKey = Str::snake($relationship->related) . '_id';
                $body .= "    \$table->foreignId('$foreignKey')\n";
                $body .= "        ->constrained()\n";
                $body .= "        ->cascadeOnDelete();\n";
            }
        }

        if ($dataPoint->hasTimestamps) {
            $body .= "    \$table->timestamps();\n";
        }

        $body .= "});";

        $method->setBody($body);
    }

    private function addDownMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $method = $class->addMethod('down')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Reverse the migrations.');

        $method->setBody("Schema::dropIfExists('{$dataPoint->tableName}');");
    }

    private function generateFieldDefinition(Field $field): string
    {
        return match ($field->type) {
            'string' => "    \$table->string('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'text' => "    \$table->text('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'integer' => "    \$table->integer('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'bigInteger' => "    \$table->bigInteger('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'boolean' => "    \$table->boolean('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'date' => "    \$table->date('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'datetime' => "    \$table->datetime('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'timestamp' => "    \$table->timestamp('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            'decimal' => "    \$table->decimal('{$field->name}', 8, 2" . $this->getFieldOptions($field->options) . ");\n",
            'json' => "    \$table->json('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
            default => "    \$table->{$field->type}('{$field->name}'" . $this->getFieldOptions($field->options) . ");\n",
        };
    }

    private function getFieldOptions(array $options): string
    {
        if (empty($options)) {
            return '';
        }

        return ', ' . implode(', ', array_map(
            fn($value) => is_string($value) ? "'$value'" : $value,
            $options
        ));
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
