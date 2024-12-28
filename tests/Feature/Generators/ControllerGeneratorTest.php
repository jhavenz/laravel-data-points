<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\ControllerType;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ControllerGenerator;
use Tests\TestCase;

class ControllerGeneratorTest extends TestCase
{
    private ControllerGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new ControllerGenerator();
        $this->tempPath = sys_get_temp_dir() . '/laravel-data-points-test';
        
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempPath);
        parent::tearDown();
    }

    /** @test */
    public function it_generates_api_controller(): void
    {
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
        $this->assertFileExists($controllerPath);
        
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('namespace App\\Http\\Controllers;', $content);
        $this->assertStringContainsString('use App\\Http\\Resources\\PostResource;', $content);
        $this->assertStringContainsString('class PostController extends Controller', $content);
        $this->assertStringContainsString('public function index()', $content);
        $this->assertStringContainsString('public function store(Request $request)', $content);
        $this->assertStringContainsString('public function show(Post $post)', $content);
        $this->assertStringContainsString('public function update(Request $request, Post $post)', $content);
        $this->assertStringContainsString('public function destroy(Post $post)', $content);
    }

    /** @test */
    public function it_generates_web_controller(): void
    {
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
        $this->assertFileExists($controllerPath);
        
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('return view(\'post.index\'', $content);
        $this->assertStringContainsString('return view(\'post.create\'', $content);
        $this->assertStringContainsString('return view(\'post.edit\'', $content);
        $this->assertStringContainsString('return redirect()->route(\'post.index\'', $content);
    }

    /** @test */
    public function it_generates_invokable_controller(): void
    {
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
        $this->assertFileExists($controllerPath);
        
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('public function __invoke(Request $request)', $content);
    }

    /** @test */
    public function it_generates_controller_with_relationships(): void
    {
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
        $this->assertFileExists($controllerPath);
        
        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('use App\\Models\\User;', $content);
    }

    private function removeDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            foreach (scandir($path) as $item) {
                if ($item == '.' || $item == '..') {
                    continue;
                }

                $this->removeDirectory($path . DIRECTORY_SEPARATOR . $item);
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
