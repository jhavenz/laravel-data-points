<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\FactoryGenerator;
use Tests\TestCase;

class FactoryGeneratorTest extends TestCase
{
    private FactoryGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new FactoryGenerator();
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
    public function it_generates_a_basic_factory(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'title', type: 'string'),
                new Field(name: 'content', type: 'text'),
                new Field(name: 'views', type: 'integer'),
            ]),
            relationships: collect([]),
            hasTimestamps: true
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Models',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $factoryPath = $this->tempPath . '/PostFactory.php';
        $this->assertFileExists($factoryPath);
        
        $expected = <<<'PHP'
            <?php

            namespace Database\Factories;

            use App\Models\Post;
            use Illuminate\Database\Eloquent\Factories\Factory;

            class PostFactory extends Factory
            {
                protected $model = Post::class;

                public function definition(): array
                {
                    return [
                        'title' => fake()->sentence(),
                        'content' => fake()->paragraphs(3, true),
                        'views' => fake()->randomNumber(4),
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($factoryPath));
    }

    /** @test */
    public function it_generates_factory_with_relationships(): void
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
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $factoryPath = $this->tempPath . '/PostFactory.php';
        $this->assertFileExists($factoryPath);
        
        $expected = <<<'PHP'
            <?php

            namespace Database\Factories;

            use App\Models\Post;
            use App\Models\User;
            use Illuminate\Database\Eloquent\Factories\Factory;

            class PostFactory extends Factory
            {
                protected $model = Post::class;

                public function definition(): array
                {
                    return [
                        'title' => fake()->sentence(),
                        'user_id' => User::factory(),
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($factoryPath));
    }

    /** @test */
    public function it_generates_factory_with_custom_field_options(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'status', type: 'string', options: [
                    'faker' => 'randomElement(["draft", "published", "archived"])',
                ]),
            ]),
            relationships: collect([]),
            hasTimestamps: true
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Models',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $factoryPath = $this->tempPath . '/PostFactory.php';
        $this->assertFileExists($factoryPath);
        
        $expected = <<<'PHP'
            <?php

            namespace Database\Factories;

            use App\Models\Post;
            use Illuminate\Database\Eloquent\Factories\Factory;

            class PostFactory extends Factory
            {
                protected $model = Post::class;

                public function definition(): array
                {
                    return [
                        'status' => fake()->randomElement(['draft', 'published', 'archived']),
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($factoryPath));
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
