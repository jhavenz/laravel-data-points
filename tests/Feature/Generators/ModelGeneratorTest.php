<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\ModelGenerator;

beforeEach(function () {
    $this->generator = new ModelGenerator();
    $this->tempPath = sys_get_temp_dir().'/laravel-data-points-test';

    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates basic model', function () {
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
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $modelPath = $this->tempPath.'/Post.php';
    expect(file_exists($modelPath))->toBeTrue();

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

    expect(file_get_contents($modelPath))->toBe($expected);
});

test('it generates model with relationships', function () {
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
            Relationship::from(RelationType::HAS_MANY, 'Comment', [
                'foreignKey' => 'post_id',
                'localKey' => 'id',
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $modelPath = $this->tempPath.'/Post.php';
    expect(file_exists($modelPath))->toBeTrue();

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

    expect(file_get_contents($modelPath))->toBe($expected);
});

test('it handles polymorphic relationships', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Comment',
        fields: collect([
            Field::from('content', 'text'),
        ]),
        relationships: collect([
            Relationship::from(RelationType::MORPH_TO, 'commentable', [
                'morphType' => 'commentable_type',
                'morphId' => 'commentable_id',
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $modelPath = $this->tempPath.'/Comment.php';
    expect(file_exists($modelPath))->toBeTrue();

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

    expect(file_get_contents($modelPath))->toBe($expected);
});

test('it handles model traits and interfaces', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
        ]),
        hasTimestamps: true,
        additionalTraits: [
            'Illuminate\\Database\\Eloquent\\SoftDeletes',
        ],
        additionalInterfaces: [
            'Illuminate\\Contracts\\Auth\\Access\\Authorizable',
        ]
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $modelPath = $this->tempPath.'/Post.php';
    expect(file_exists($modelPath))->toBeTrue();

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

    expect(file_get_contents($modelPath))->toBe($expected);
});

test('it handles custom casts and attributes', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('metadata', 'array'),
            Field::from('published', 'boolean', [
                'default' => false,
            ]),
            Field::from('slug', 'string', [
                'unique' => true,
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $modelPath = $this->tempPath.'/Post.php';
    expect(file_exists($modelPath))->toBeTrue();

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

    expect(file_get_contents($modelPath))->toBe($expected);
});

test('it handles circular relationships', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'User',
        fields: collect([
            Field::from('name', 'string'),
        ]),
        relationships: collect([
            Relationship::from(RelationType::HAS_MANY, 'Post', [
                'foreignKey' => 'user_id',
                'localKey' => 'id',
            ]),
        ]),
        hasTimestamps: true
    );

    $postDataPoint = new DataPoint(
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

    $collection = new DataPointCollection($dataPoint, $postDataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $userPath = $this->tempPath.'/User.php';
    $postPath = $this->tempPath.'/Post.php';

    expect(file_exists($userPath))->toBeTrue()
        ->and(file_exists($postPath))->toBeTrue();

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

    expect(file_get_contents($userPath))->toBe($expectedUser)
        ->and(file_get_contents($postPath))->toBe($expectedPost);
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
