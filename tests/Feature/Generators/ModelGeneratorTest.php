<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ModelGenerator;
use Tests\TestCase;

class ModelGeneratorTest extends TestCase
{
    private ModelGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new ModelGenerator();
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
    public function it_generates_a_basic_model(): void
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
        $modelPath = $this->tempPath . '/Post.php';
        $this->assertFileExists($modelPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;

            class Post extends Model
            {
                protected $fillable = [
                    'title',
                    'content',
                ];

                protected $casts = [
                    'title' => 'string',
                    'content' => 'string',
                ];
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($modelPath));
    }

    /** @test */
    public function it_generates_model_with_relationships(): void
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
        $modelPath = $this->tempPath . '/Post.php';
        $this->assertFileExists($modelPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\BelongsTo;
            use Illuminate\Database\Eloquent\Relations\HasMany;

            class Post extends Model
            {
                protected $fillable = [
                    'title',
                    'user_id',
                ];

                protected $casts = [
                    'title' => 'string',
                ];

                public function user(): BelongsTo
                {
                    return $this->belongsTo(User::class, 'user_id', 'id');
                }

                public function comments(): HasMany
                {
                    return $this->hasMany(Comment::class, 'post_id', 'id');
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($modelPath));
    }

    /** @test */
    public function it_generates_model_with_custom_options(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'title', type: 'string', options: [
                    'casts' => 'array',
                    'hidden' => true,
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
        $modelPath = $this->tempPath . '/Post.php';
        $this->assertFileExists($modelPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;

            class Post extends Model
            {
                protected $fillable = [
                    'title',
                ];

                protected $casts = [
                    'title' => 'array',
                ];

                protected $hidden = [
                    'title',
                ];
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($modelPath));
    }

    /** @test */
    public function it_handles_polymorphic_relationships(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Comment',
            fields: collect([
                new Field(name: 'content', type: 'text'),
            ]),
            relationships: collect([
                new Relationship(
                    type: RelationType::MORPH_TO,
                    related: 'commentable',
                    options: new RelationshipOptions(
                        morphName: 'commentable',
                        morphType: 'commentable_type',
                        morphId: 'commentable_id'
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
        $modelPath = $this->tempPath . '/Comment.php';
        $this->assertFileExists($modelPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\MorphTo;

            class Comment extends Model
            {
                protected $fillable = [
                    'content',
                    'commentable_type',
                    'commentable_id',
                ];

                protected $casts = [
                    'content' => 'string',
                ];

                public function commentable(): MorphTo
                {
                    return $this->morphTo('commentable', 'commentable_type', 'commentable_id');
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($modelPath));
    }

    /** @test */
    public function it_handles_model_traits_and_interfaces(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'title', type: 'string'),
            ]),
            relationships: collect([]),
            hasTimestamps: true,
            options: [
                'traits' => ['Illuminate\\Database\\Eloquent\\SoftDeletes'],
                'interfaces' => ['Illuminate\\Contracts\\Auth\\Access\\Authorizable'],
            ]
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Models',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $modelPath = $this->tempPath . '/Post.php';
        $this->assertFileExists($modelPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Contracts\Auth\Access\Authorizable;
            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\SoftDeletes;

            class Post extends Model implements Authorizable
            {
                use SoftDeletes;

                protected $fillable = [
                    'title',
                ];

                protected $casts = [
                    'title' => 'string',
                ];
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($modelPath));
    }

    /** @test */
    public function it_handles_custom_casts_and_attributes(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'metadata', type: 'json', options: [
                    'cast' => 'array',
                    'hidden' => true,
                ]),
                new Field(name: 'published', type: 'boolean', options: [
                    'cast' => 'boolean',
                    'default' => false,
                ]),
                new Field(name: 'slug', type: 'string', options: [
                    'appends' => true,
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
        $modelPath = $this->tempPath . '/Post.php';
        $this->assertFileExists($modelPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Support\Str;

            class Post extends Model
            {
                protected $fillable = [
                    'metadata',
                    'published',
                    'slug',
                ];

                protected $casts = [
                    'metadata' => 'array',
                    'published' => 'boolean',
                    'slug' => 'string',
                ];

                protected $hidden = [
                    'metadata',
                ];

                protected $appends = [
                    'slug',
                ];

                protected $attributes = [
                    'published' => false,
                ];

                public function getSlugAttribute(): string
                {
                    return Str::slug($this->attributes['slug']);
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($modelPath));
    }

    /** @test */
    public function it_handles_circular_relationships(): void
    {
        // Arrange
        $dataPoints = new DataPointCollection(collect([
            new DataPoint(
                name: 'User',
                fields: collect([
                    new Field(name: 'name', type: 'string'),
                ]),
                relationships: collect([
                    new Relationship(
                        type: RelationType::HAS_MANY,
                        related: 'Post',
                        options: new RelationshipOptions(
                            foreignKey: 'user_id',
                            localKey: 'id'
                        )
                    ),
                ]),
                hasTimestamps: true
            ),
            new DataPoint(
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
            ),
        ]));

        $options = new TemplateOptions(
            namespace: 'App\\Models',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($dataPoints, $options);

        // Assert
        $userPath = $this->tempPath . '/User.php';
        $postPath = $this->tempPath . '/Post.php';
        $this->assertFileExists($userPath);
        $this->assertFileExists($postPath);
        
        $expectedUser = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\HasMany;

            class User extends Model
            {
                protected $fillable = [
                    'name',
                ];

                protected $casts = [
                    'name' => 'string',
                ];

                public function posts(): HasMany
                {
                    return $this->hasMany(Post::class, 'user_id', 'id');
                }
            }
            PHP;
        
        $expectedPost = <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Eloquent\Relations\BelongsTo;

            class Post extends Model
            {
                protected $fillable = [
                    'title',
                    'user_id',
                ];

                protected $casts = [
                    'title' => 'string',
                ];

                public function user(): BelongsTo
                {
                    return $this->belongsTo(User::class, 'user_id', 'id');
                }
            }
            PHP;
        
        $this->assertEquals($expectedUser, file_get_contents($userPath));
        $this->assertEquals($expectedPost, file_get_contents($postPath));
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
