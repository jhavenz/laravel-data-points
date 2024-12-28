<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\ControllerType;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ControllerGenerator;

beforeEach(function () {
    $this->generator = new ControllerGenerator();
    $this->tempPath = sys_get_temp_dir().'/laravel-data-points-test';

    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates api controller', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
            Field::from('content', 'text'),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        controllerType: ControllerType::API_RESOURCE,
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath.'/PostController.php';
    expect(file_exists($controllerPath))->toBeTrue();

    $content = file_get_contents($controllerPath);
    expect($content)->toContain('namespace App\\Http\\Controllers;')
        ->and($content)->toContain('use App\\Http\\Resources\\PostResource;')
        ->and($content)->toContain('class PostController extends Controller')
        ->and($content)->toContain('public function index()')
        ->and($content)->toContain('public function store(Request $request)')
        ->and($content)->toContain('public function show(Post $post)')
        ->and($content)->toContain('public function update(Request $request, Post $post)')
        ->and($content)->toContain('public function destroy(Post $post)');
});

test('it generates web controller', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        controllerType: ControllerType::RESOURCE,
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath.'/PostController.php';
    expect(file_exists($controllerPath))->toBeTrue();

    $content = file_get_contents($controllerPath);
    expect($content)->toContain('return view(\'post.index\'')
        ->and($content)->toContain('return view(\'post.create\'')
        ->and($content)->toContain('return view(\'post.edit\'')
        ->and($content)->toContain('return redirect()->route(\'post.index\'');
});

test('it generates controller with relationships', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
        ]),
        relationships: collect([
            Relationship::from(RelationType::BELONGS_TO, 'User', [
                'foreignKey' => 'user_id',
                'localKey' => 'id',
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        controllerType: ControllerType::API_RESOURCE,
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath.'/PostController.php';
    expect(file_exists($controllerPath))->toBeTrue();

    $content = file_get_contents($controllerPath);
    expect($content)->toContain('use App\\Models\\User;');
});

function removeDirectory(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_dir($path)) {
        foreach (scandir($path) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            removeDirectory($path.DIRECTORY_SEPARATOR.$item);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}
