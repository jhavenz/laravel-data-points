<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\SeederGenerator;
use Tests\TestCase;

class SeederGeneratorTest extends TestCase
{
    private SeederGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new SeederGenerator();
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
    public function it_generates_basic_seeder(): void
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
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $seederPath = $this->tempPath . '/PostSeeder.php';
        $this->assertFileExists($seederPath);
        
        $expected = <<<'PHP'
            <?php

            namespace Database\Seeders;

            use App\Models\Post;
            use Illuminate\Database\Seeder;

            class PostSeeder extends Seeder
            {
                public function run(): void
                {
                    $count = 10;
                    Post::factory($count)->create();
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($seederPath));
    }

    /** @test */
    public function it_generates_seeder_with_relationships(): void
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
                new Relationship(
                    type: RelationType::BELONGS_TO,
                    related: 'Category',
                    options: new RelationshipOptions(
                        foreignKey: 'category_id',
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
        $seederPath = $this->tempPath . '/PostSeeder.php';
        $this->assertFileExists($seederPath);
        
        $expected = <<<'PHP'
            <?php

            namespace Database\Seeders;

            use App\Models\Post;
            use App\Models\User;
            use App\Models\Category;
            use Illuminate\Database\Seeder;

            class PostSeeder extends Seeder
            {
                public function run(): void
                {
                    $count = 10;
                    $relatedUser = User::factory($count)->create();
                    $relatedCategory = Category::factory($count)->create();

                    Post::factory($count)
                        ->sequence(fn($sequence) => [
                            'user_id' => $relatedUser[$sequence->index]->id,
                            'category_id' => $relatedCategory[$sequence->index]->id,
                        ])
                        ->create();
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($seederPath));
    }

    /** @test */
    public function it_updates_database_seeder(): void
    {
        // Arrange
        $dataPoints = new DataPointCollection(collect([
            new DataPoint(
                name: 'Category',
                fields: collect([new Field(name: 'name', type: 'string')]),
                relationships: collect([]),
                hasTimestamps: true
            ),
            new DataPoint(
                name: 'Post',
                fields: collect([new Field(name: 'title', type: 'string')]),
                relationships: collect([
                    new Relationship(
                        type: RelationType::BELONGS_TO,
                        related: 'Category',
                        options: new RelationshipOptions(
                            foreignKey: 'category_id',
                            localKey: 'id'
                        )
                    ),
                ]),
                hasTimestamps: true
            ),
        ]));

        $options = new TemplateOptions(
            namespace: 'App\\Models',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($dataPoints, $options);

        // Assert
        $seederPath = $this->tempPath . '/DatabaseSeeder.php';
        $this->assertFileExists($seederPath);
        
        $expected = <<<'PHP'
            <?php

            namespace Database\Seeders;

            use Database\Seeders\CategorySeeder;
            use Database\Seeders\PostSeeder;
            use Illuminate\Database\Seeder;

            class DatabaseSeeder extends Seeder
            {
                public function run(): void
                {
                    $this->call(CategorySeeder::class);
                    $this->call(PostSeeder::class);
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($seederPath));
        // Category should be seeded before Post due to dependency
        $this->assertGreaterThan(
            strpos($expected, 'CategorySeeder::class'),
            strpos($expected, 'PostSeeder::class')
        );
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
