<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ResourceGenerator;
use Tests\TestCase;

class ResourceGeneratorTest extends TestCase
{
    private ResourceGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new ResourceGenerator();
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
    public function it_generates_basic_resource(): void
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
        $resourcePath = $this->tempPath . '/PostResource.php';
        $this->assertFileExists($resourcePath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Http\Resources;

            use Illuminate\Http\Resources\Json\JsonResource;

            class PostResource extends JsonResource
            {
                public function toArray($request): array
                {
                    return [
                        'id' => $this->id,
                        'title' => $this->title,
                        'content' => $this->content,
                        'created_at' => $this->created_at,
                        'updated_at' => $this->updated_at,
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($resourcePath));
    }

    /** @test */
    public function it_generates_resource_with_relationships(): void
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
                    type: RelationType::HAS_MANY,
                    related: 'Comment',
                    options: new RelationshipOptions(
                        foreignKey: 'post_id',
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
        $resourcePath = $this->tempPath . '/PostResource.php';
        $this->assertFileExists($resourcePath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Http\Resources;

            use App\Http\Resources\UserResource;
            use App\Http\Resources\CommentResource;
            use Illuminate\Http\Resources\Json\JsonResource;

            class PostResource extends JsonResource
            {
                public function toArray($request): array
                {
                    return [
                        'id' => $this->id,
                        'title' => $this->title,
                        'created_at' => $this->created_at,
                        'updated_at' => $this->updated_at,
                        'user' => new UserResource($this->whenLoaded('user')),
                        'comments' => CommentResource::collection($this->whenLoaded('comments')),
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($resourcePath));
    }

    /** @test */
    public function it_generates_resource_collection(): void
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
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $collectionPath = $this->tempPath . '/PostCollection.php';
        $this->assertFileExists($collectionPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Http\Resources;

            use Illuminate\Http\Resources\Json\ResourceCollection;

            class PostCollection extends ResourceCollection
            {
                public function toArray($request): array
                {
                    return [
                        'data' => $this->collection,
                        'meta' => [
                            'total' => $this->collection->count(),
                        ],
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($collectionPath));
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
