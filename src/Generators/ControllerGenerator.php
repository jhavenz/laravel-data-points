<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\ControllerType;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class ControllerGenerator implements Generator
{
    public string $type {
        get => 'controller';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): void
    {
        foreach ($dataPoints as $dataPoint) {
            $this->generateController($dataPoint, $options);
        }
    }

    private function generateController(DataPoint $dataPoint, TemplateOptions $options): void
    {
        $className = $dataPoint->name . 'Controller';

        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\\Http\\Controllers');
        $this->addImports($namespace, $dataPoint, $options);

        $class = $namespace->addClass($className)
            ->setExtends('App\\Http\\Controllers\\Controller');

        $this->addConstructor($class, $dataPoint);
        $this->addMethods($class, $dataPoint, $options);

        $printer = new PsrPrinter;
        $content = (string) $file;

        $path = app_path('Http/Controllers/' . $className . '.php');

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    private function addImports($namespace, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $namespace->addUse('App\\Http\\Controllers\\Controller');
        $namespace->addUse('Illuminate\\Http\\Request');
        $namespace->addUse('Illuminate\\Http\\Response');
        $namespace->addUse($this->getNamespace($dataPoint, $options) . '\\' . $dataPoint->name);

        if ($options->controllerType === ControllerType::API) {
            $namespace->addUse('App\\Http\\Resources\\' . $dataPoint->name . 'Resource');
            $namespace->addUse('App\\Http\\Resources\\' . $dataPoint->name . 'Collection');
        }
    }

    private function addConstructor(ClassType $class, DataPoint $dataPoint): void
    {
        $constructor = $class->addMethod('__construct');
        $constructor->setPublic();
    }

    private function addMethods(ClassType $class, DataPoint $dataPoint, TemplateOptions $options): void
    {
        match ($options->controllerType) {
            ControllerType::API => $this->addApiMethods($class, $dataPoint),
            ControllerType::WEB => $this->addWebMethods($class, $dataPoint),
            ControllerType::INVOKABLE => $this->addInvokableMethod($class, $dataPoint),
        };
    }

    private function addApiMethods(ClassType $class, DataPoint $dataPoint): void
    {
        // Index
        $index = $class->addMethod('index')
            ->setPublic()
            ->setReturnType('Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection')
            ->addComment('Display a listing of the resource.')
            ->addComment('@return \\Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection');

        $index->setBody(
            "return {$dataPoint->name}Resource::collection({$dataPoint->name}::paginate());"
        );

        // Store
        $store = $class->addMethod('store')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->setReturnType($dataPoint->name . 'Resource')
            ->addComment('Store a newly created resource in storage.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@return \\' . $dataPoint->name . 'Resource');

        $store->setBody(
            "\$validated = \$request->validate([]);\n\n" .
            "return new {$dataPoint->name}Resource(\n" .
            "    {$dataPoint->name}::create(\$validated)\n" .
            ");"
        );

        // Show
        $show = $class->addMethod('show')
            ->setPublic()
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType($dataPoint->name . 'Resource')
            ->addComment('Display the specified resource.')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . lcfirst($dataPoint->name))
            ->addComment('@return \\' . $dataPoint->name . 'Resource');

        $show->setBody(
            "return new {$dataPoint->name}Resource(\${$varName});"
        );

        // Update
        $update = $class->addMethod('update')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType($dataPoint->name . 'Resource')
            ->addComment('Update the specified resource in storage.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . lcfirst($dataPoint->name))
            ->addComment('@return \\' . $dataPoint->name . 'Resource');

        $update->setBody(
            "\$validated = \$request->validate([]);\n\n" .
            "\${$varName}->update(\$validated);\n\n" .
            "return new {$dataPoint->name}Resource(\${$varName});"
        );

        // Destroy
        $destroy = $class->addMethod('destroy')
            ->setPublic()
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType('Response')
            ->addComment('Remove the specified resource from storage.')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . lcfirst($dataPoint->name))
            ->addComment('@return \\Illuminate\\Http\\Response');

        $destroy->setBody(
            "\${$varName}->delete();\n\n" .
            "return response()->noContent();"
        );
    }

    private function addWebMethods(ClassType $class, DataPoint $dataPoint): void
    {
        $varName = lcfirst($dataPoint->name);

        // Index
        $index = $class->addMethod('index')
            ->setPublic()
            ->setReturnType('Illuminate\\View\\View')
            ->addComment('Display a listing of the resource.')
            ->addComment('@return \\Illuminate\\View\\View');

        $index->setBody(
            "return view('{$varName}.index', [\n" .
            "    '{$varName}s' => {$dataPoint->name}::paginate(),\n" .
            "]);"
        );

        // Create
        $create = $class->addMethod('create')
            ->setPublic()
            ->setReturnType('Illuminate\\View\\View')
            ->addComment('Show the form for creating a new resource.')
            ->addComment('@return \\Illuminate\\View\\View');

        $create->setBody(
            "return view('{$varName}.create');"
        );

        // Store
        $store = $class->addMethod('store')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->setReturnType('Illuminate\\Http\\RedirectResponse')
            ->addComment('Store a newly created resource in storage.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@return \\Illuminate\\Http\\RedirectResponse');

        $store->setBody(
            "\$validated = \$request->validate([]);\n\n" .
            "{$dataPoint->name}::create(\$validated);\n\n" .
            "return redirect()\n" .
            "    ->route('{$varName}.index')\n" .
            "    ->with('success', '{$dataPoint->name} created successfully.');"
        );

        // Show
        $show = $class->addMethod('show')
            ->setPublic()
            ->addParameter($varName)
            ->setType($dataPoint->name)
            ->setReturnType('Illuminate\\View\\View')
            ->addComment('Display the specified resource.')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . $varName)
            ->addComment('@return \\Illuminate\\View\\View');

        $show->setBody(
            "return view('{$varName}.show', [\n" .
            "    '{$varName}' => \${$varName},\n" .
            "]);"
        );

        // Edit
        $edit = $class->addMethod('edit')
            ->setPublic()
            ->addParameter($varName)
            ->setType($dataPoint->name)
            ->setReturnType('Illuminate\\View\\View')
            ->addComment('Show the form for editing the specified resource.')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . $varName)
            ->addComment('@return \\Illuminate\\View\\View');

        $edit->setBody(
            "return view('{$varName}.edit', [\n" .
            "    '{$varName}' => \${$varName},\n" .
            "]);"
        );

        // Update
        $update = $class->addMethod('update')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->addParameter($varName)
            ->setType($dataPoint->name)
            ->setReturnType('Illuminate\\Http\\RedirectResponse')
            ->addComment('Update the specified resource in storage.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . $varName)
            ->addComment('@return \\Illuminate\\Http\\RedirectResponse');

        $update->setBody(
            "\$validated = \$request->validate([]);\n\n" .
            "\${$varName}->update(\$validated);\n\n" .
            "return redirect()\n" .
            "    ->route('{$varName}.show', \${$varName})\n" .
            "    ->with('success', '{$dataPoint->name} updated successfully.');"
        );

        // Destroy
        $destroy = $class->addMethod('destroy')
            ->setPublic()
            ->addParameter($varName)
            ->setType($dataPoint->name)
            ->setReturnType('Illuminate\\Http\\RedirectResponse')
            ->addComment('Remove the specified resource from storage.')
            ->addComment('@param  \\' . $this->getNamespace($dataPoint) . '\\' . $dataPoint->name . '  $' . $varName)
            ->addComment('@return \\Illuminate\\Http\\RedirectResponse');

        $destroy->setBody(
            "\${$varName}->delete();\n\n" .
            "return redirect()\n" .
            "    ->route('{$varName}.index')\n" .
            "    ->with('success', '{$dataPoint->name} deleted successfully.');"
        );
    }

    private function addInvokableMethod(ClassType $class, DataPoint $dataPoint): void
    {
        $invoke = $class->addMethod('__invoke')
            ->setPublic()
            ->addParameter('request')
            ->setType('Illuminate\\Http\\Request')
            ->setReturnType('Illuminate\\View\\View')
            ->addComment('Handle the incoming request.')
            ->addComment('@param  \\Illuminate\\Http\\Request  $request')
            ->addComment('@return \\Illuminate\\View\\View');

        $varName = lcfirst($dataPoint->name);

        $invoke->setBody(
            "return view('{$varName}.index', [\n" .
            "    '{$varName}s' => {$dataPoint->name}::paginate(),\n" .
            "]);"
        );
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
