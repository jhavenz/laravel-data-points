<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

readonly class MigrationGenerator
{
    public function __construct(
        private DataPoint $dataPoint
    ) {}

    public function generate(): void
    {
        $className = 'Create' . Str::studly($this->dataPoint->tableName) . 'Table';

        $namespace = new PhpNamespace('Database\\Migrations');
        $namespace->addUse('Illuminate\\Database\\Migrations\\Migration');
        $namespace->addUse('Illuminate\\Database\\Schema\\Blueprint');
        $namespace->addUse('Illuminate\\Support\\Facades\\Schema');

        $class = $namespace
            ->addClass($className)
            ->setExtends('Illuminate\\Database\\Migrations\\Migration');

        $this->addUpMethod($class);
        $this->addDownMethod($class);

        $printer = new PsrPrinter;
        $content = "<?php\n\n" . $printer->printNamespace($namespace);

        $filename = date('Y_m_d_His') . '_create_' .
            $this->dataPoint->tableName . '_table.php';

        $path = database_path('migrations/' . $filename);

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    private function addUpMethod(ClassType $class): void
    {
        $method = $class
            ->addMethod('up')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Run the migrations.');

        $body = "Schema::create('{$this->dataPoint->tableName}', function (Blueprint \$table) {\n";
        $body .= "    \$table->id();\n";

        foreach ($this->dataPoint as $name => $field) {
            $body .= $this->generateFieldDefinition($name, $field);
        }

        // Add foreign keys for relationships
        foreach ($this->dataPoint as $relationship) {
            if ($relationship['type'] === 'belongsTo') {
                $foreignKey = Str::snake($relationship['related']) . '_id';
                $body .= "    \$table->foreignId('$foreignKey')\n";
                $body .= "        ->constrained()\n";
                $body .= "        ->cascadeOnDelete();\n";
            }
        }

        if ($this->dataPoint->hasTimestamps) {
            $body .= "    \$table->timestamps();\n";
        }

        $body .= "});";

        $method->setBody($body);
    }

    private function addDownMethod(ClassType $class): void
    {
        $method = $class->addMethod('down')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Reverse the migrations.');

        $method->setBody("Schema::dropIfExists('{$this->dataPoint->tableName}');");
    }

    private function generateFieldDefinition(string $name, array $field): string
    {
        return match ($field['type']) {
            'string' => "    \$table->string('$name'" . $this->getFieldOptions($field) . ");\n",
            'text' => "    \$table->text('$name'" . $this->getFieldOptions($field) . ");\n",
            'integer' => "    \$table->integer('$name'" . $this->getFieldOptions($field) . ");\n",
            'bigInteger' => "    \$table->bigInteger('$name'" . $this->getFieldOptions($field) . ");\n",
            'boolean' => "    \$table->boolean('$name'" . $this->getFieldOptions($field) . ");\n",
            'date' => "    \$table->date('$name'" . $this->getFieldOptions($field) . ");\n",
            'datetime' => "    \$table->datetime('$name'" . $this->getFieldOptions($field) . ");\n",
            'timestamp' => "    \$table->timestamp('$name'" . $this->getFieldOptions($field) . ");\n",
            'decimal' => "    \$table->decimal('$name', 8, 2" . $this->getFieldOptions($field) . ");\n",
            'json' => "    \$table->json('$name'" . $this->getFieldOptions($field) . ");\n",
            default => "    \$table->{$field['type']}('$name'" . $this->getFieldOptions($field) . ");\n",
        };
    }

    private function getFieldOptions(array $field): string
    {
        if (empty($field['options'])) {
            return '';
        }

        return ', ' . implode(', ', array_map(
            fn($value) => is_string($value) ? "'$value'" : $value,
            $field['options']
        ));
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
