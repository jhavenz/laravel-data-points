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

class PolicyGenerator implements Generator
{
    public string $type {
        get => 'policy';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): void
    {
        foreach ($dataPoints as $dataPoint) {
            $this->generatePolicy($dataPoint, $options);
        }

        $this->updateAuthServiceProvider($dataPoints, $options);
    }

    private function generatePolicy(DataPoint $dataPoint, TemplateOptions $options): void
    {
        $className = $dataPoint->name . 'Policy';

        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\\Policies');
        $this->addImports($namespace, $dataPoint, $options);

        $class = $namespace->addClass($className);

        $this->addConstructor($class);
        $this->addPolicyMethods($class, $dataPoint, $options);

        $printer = new PsrPrinter;
        $content = (string) $file;

        $path = app_path('Policies/' . $className . '.php');

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    private function addImports($namespace, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $namespace->addUse('App\\Models\\User');
        $modelNamespace = $this->getNamespace($dataPoint, $options);
        $namespace->addUse($modelNamespace . '\\' . $dataPoint->name);
    }

    private function addConstructor(ClassType $class): void
    {
        $constructor = $class->addMethod('__construct')
            ->setPublic();
    }

    private function addPolicyMethods(ClassType $class, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $modelNamespace = $this->getNamespace($dataPoint, $options);
        
        // ViewAny
        $viewAny = $class->addMethod('viewAny')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->setReturnType('bool')
            ->addComment('Determine whether the user can view any models.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment('@return bool');

        $viewAny->setBody('return true;');

        // View
        $view = $class->addMethod('view')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType('bool')
            ->addComment('Determine whether the user can view the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$" . lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $view->setBody('return true;');

        // Create
        $create = $class->addMethod('create')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->setReturnType('bool')
            ->addComment('Determine whether the user can create models.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment('@return bool');

        $create->setBody('return true;');

        // Update
        $update = $class->addMethod('update')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType('bool')
            ->addComment('Determine whether the user can update the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$" . lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $update->setBody('return true;');

        // Delete
        $delete = $class->addMethod('delete')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType('bool')
            ->addComment('Determine whether the user can delete the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$" . lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $delete->setBody('return true;');

        // Restore
        $restore = $class->addMethod('restore')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType('bool')
            ->addComment('Determine whether the user can restore the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$" . lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $restore->setBody('return true;');

        // ForceDelete
        $forceDelete = $class->addMethod('forceDelete')
            ->setPublic()
            ->addParameter('user')
            ->setType('User')
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name)
            ->setReturnType('bool')
            ->addComment('Determine whether the user can permanently delete the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$" . lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $forceDelete->setBody('return true;');
    }

    private function updateAuthServiceProvider(DataPointCollection $dataPoints, TemplateOptions $options): void
    {
        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\\Providers');
        $namespace->addUse('Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider');
        $namespace->addUse('Illuminate\\Support\\Facades\\Gate');

        foreach ($dataPoints as $dataPoint) {
            $namespace->addUse($this->getNamespace($dataPoint, $options) . '\\' . $dataPoint->name);
            $namespace->addUse('App\\Policies\\' . $dataPoint->name . 'Policy');
        }

        $class = $namespace->addClass('AuthServiceProvider')
            ->setExtends('Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider');

        // Add policies property
        $policies = $class->addProperty('policies')
            ->setProtected()
            ->setType('array')
            ->setValue([]);

        $policiesValue = [];
        foreach ($dataPoints as $dataPoint) {
            $policiesValue[$dataPoint->name . '::class'] = $dataPoint->name . 'Policy::class';
        }
        $policies->setValue($policiesValue);

        // Add boot method
        $boot = $class->addMethod('boot')
            ->setPublic()
            ->setReturnType('void')
            ->addComment('Register any authentication / authorization services.')
            ->addComment('@return void');

        $boot->setBody(
            "\$this->registerPolicies();\n\n" .
            "//\n"
        );

        $printer = new PsrPrinter;
        $content = (string) $file;

        $path = app_path('Providers/AuthServiceProvider.php');
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
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
