<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ResourceGenerator;

beforeEach(function () {
    $this->generator = new ResourceGenerator();
    $this->tempPath = sys_get_temp_dir() . '/laravel-data-points-test';
    
    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates basic resource', function () {
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
    expect(file_exists($resourcePath))->toBeTrue();
    
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
    
    expect(file_get_contents($resourcePath))->toBe($expected);
});

test('it generates resource with relationships', function () {
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
    expect(file_exists($resourcePath))->toBeTrue();
    
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
    
    expect(file_get_contents($resourcePath))->toBe($expected);
});

test('it generates resource collection', function () {
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
    expect(file_exists($collectionPath))->toBeTrue();
    
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
    
    expect(file_get_contents($collectionPath))->toBe($expected);
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
