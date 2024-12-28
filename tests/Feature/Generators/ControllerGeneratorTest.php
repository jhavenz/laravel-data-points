<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\ControllerType;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ControllerGenerator;

beforeEach(function () {
    $this->generator = new ControllerGenerator();
    $this->tempPath = sys_get_temp_dir() . '/laravel-data-points-test';
    
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
            new Field(name: 'title', type: 'string'),
            new Field(name: 'content', type: 'text'),
        ]),
        relationships: collect([]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection(collect([$dataPoint]));
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath,
        controllerType: ControllerType::API
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath . '/PostController.php';
    expect(file_exists($controllerPath))->toBeTrue();
    
    $content = file_get_contents($controllerPath);
    expect($content)->toContain('namespace App\\Http\\Controllers;');
    expect($content)->toContain('use App\\Http\\Resources\\PostResource;');
    expect($content)->toContain('class PostController extends Controller');
    expect($content)->toContain('public function index()');
    expect($content)->toContain('public function store(Request $request)');
    expect($content)->toContain('public function show(Post $post)');
    expect($content)->toContain('public function update(Request $request, Post $post)');
    expect($content)->toContain('public function destroy(Post $post)');
});

test('it generates web controller', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            new Field(name: 'title', type: 'string'),
        ]),
        relationships: collect([]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection(collect([$dataPoint]));
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath,
        controllerType: ControllerType::WEB
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath . '/PostController.php';
    expect(file_exists($controllerPath))->toBeTrue();
    
    $content = file_get_contents($controllerPath);
    expect($content)->toContain('return view(\'post.index\'');
    expect($content)->toContain('return view(\'post.create\'');
    expect($content)->toContain('return view(\'post.edit\'');
    expect($content)->toContain('return redirect()->route(\'post.index\'');
});

test('it generates invokable controller', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            new Field(name: 'title', type: 'string'),
        ]),
        relationships: collect([]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection(collect([$dataPoint]));
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath,
        controllerType: ControllerType::INVOKABLE
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath . '/PostController.php';
    expect(file_exists($controllerPath))->toBeTrue();
    
    $content = file_get_contents($controllerPath);
    expect($content)->toContain('public function __invoke(Request $request)');
});

test('it generates controller with relationships', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            new Field(name: 'title', type: 'string'),
        ]),
        relationships: collect([
            new Relationship(
                type: RelationType::BELONGS_TO,
                related: 'User',
                options: new RelationshipOptions(
                    foreignKey: 'user_id',
                    localKey: 'id'
                )
            ),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection(collect([$dataPoint]));
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath,
        controllerType: ControllerType::API
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $controllerPath = $this->tempPath . '/PostController.php';
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

            removeDirectory($path . DIRECTORY_SEPARATOR . $item);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}
