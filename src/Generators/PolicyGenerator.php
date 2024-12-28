<?php

namespace DataPoints\LaravelDataPoints\Generators;

use DataPoints\LaravelDataPoints\Contracts\Generator;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\GeneratedArtifact;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use Illuminate\Support\Collection;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class PolicyGenerator implements Generator
{
    public string $type {
        get => 'policy';
    }

    public function generate(DataPointCollection $dataPoints, TemplateOptions $options): Collection
    {
        $result = collect();
        foreach ($dataPoints as $dataPoint) {
            $result[] = $this->generatePolicy($dataPoint, $options);
        }

        return $result;
    }

    private function generatePolicy(DataPoint $dataPoint, TemplateOptions $options): GeneratedArtifact
    {
        $className = $dataPoint->name.'Policy';

        $file = new PhpFile;
        $file->setStrictTypes();

        $namespace = $file->addNamespace('App\\Policies');
        $this->addImports($namespace, $dataPoint, $options);

        $class = $namespace->addClass($className);

        $this->addConstructor($class);
        $this->addPolicyMethods($class, $dataPoint, $options);

        return new GeneratedArtifact(
            app_path('Policies/'.$className.'.php'),
            (string)$file
        );
    }

    private function addImports($namespace, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $namespace->addUse('App\\Models\\User');
        $modelNamespace = $this->getNamespace($dataPoint, $options);
        $namespace->addUse($modelNamespace.'\\'.$dataPoint->name);
    }

    private function addConstructor(ClassType $class): void
    {
        $class->addMethod('__construct')->setPublic();
    }

    private function addPolicyMethods(ClassType $class, DataPoint $dataPoint, TemplateOptions $options): void
    {
        $modelNamespace = $this->getNamespace($dataPoint, $options);

        // ViewAny
        $viewAnyMethod = $class
            ->addMethod('viewAny')
            ->setPublic();

        $viewAnyMethod
            ->addParameter('user')
            ->setType('User');

        $viewAnyMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can view any models.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment('@return bool');

        $viewAnyMethod->setBody('return true;');

        // View
        $viewMethod = $class->addMethod('view')
            ->setPublic();

        $viewMethod
            ->addParameter('user')
            ->setType('User');

        $viewMethod
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name);

        $viewMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can view the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$".lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $viewMethod->setBody('return true;');

        // Create
        $createMethod = $class
            ->addMethod('create')
            ->setPublic();

        $createMethod
            ->addParameter('user')
            ->setType('User');

        $createMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can create models.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment('@return bool');

        $createMethod->setBody('return true;');

        // Update
        $updateMethod = $class
            ->addMethod('update')
            ->setPublic();

        $updateMethod
            ->addParameter('user')
            ->setType('User');

        $updateMethod
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name);

        $updateMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can update the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$".lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $updateMethod->setBody('return true;');

        // Delete
        $deleteMethod = $class
            ->addMethod('delete')
            ->setPublic();

        $deleteMethod
            ->addParameter('user')
            ->setType('User');

        $deleteMethod
            ->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name);

        $deleteMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can delete the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$".lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $deleteMethod->setBody('return true;');

        $restoreMethod = $class
            ->addMethod('restore')
            ->setPublic();

        $restoreMethod
            ->addParameter('user')
            ->setType('User');

        $restoreMethod->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name);

        $restoreMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can restore the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$".lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $restoreMethod->setBody('return true;');

        $forceDeleteMethod = $class
            ->addMethod('forceDelete')
            ->setPublic();

        $forceDeleteMethod
            ->addParameter('user')
            ->setType('User');

        $forceDeleteMethod->addParameter(lcfirst($dataPoint->name))
            ->setType($dataPoint->name);

        $forceDeleteMethod
            ->setReturnType('bool')
            ->addComment('Determine whether the user can permanently delete the model.')
            ->addComment('@param  \\App\\Models\\User  $user')
            ->addComment("@param  \\{$modelNamespace}\\{$dataPoint->name}  \$".lcfirst($dataPoint->name))
            ->addComment('@return bool');

        $forceDeleteMethod->setBody('return true;');
    }

    private function getNamespace(DataPoint $dataPoint, ?TemplateOptions $options = null): string
    {
        return $options?->namespace ?? 'App\\Models';
    }
}
