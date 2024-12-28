<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\FactoryGenerator;

beforeEach(function () {
    $this->generator = new FactoryGenerator();
    $this->tempPath = sys_get_temp_dir().'/laravel-data-points-test';

    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates basic factory', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
            Field::from('content', 'text'),
            Field::from('views', 'integer'),
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
    $factoryPath = $this->tempPath.'/PostFactory.php';
    expect(file_exists($factoryPath))->toBeTrue();

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
                    'title' => $this->faker->sentence(),
                    'content' => $this->faker->paragraphs(3, true),
                    'views' => $this->faker->randomNumber(4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        PHP;

    expect(file_get_contents($factoryPath))->toBe($expected);
});

test('it generates factory with relationships', function () {
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
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $factoryPath = $this->tempPath.'/PostFactory.php';
    expect(file_exists($factoryPath))->toBeTrue();

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
                    'title' => $this->faker->sentence(),
                    'user_id' => User::factory(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        PHP;

    expect(file_get_contents($factoryPath))->toBe($expected);
});

test('it generates factory with custom field options', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('status', 'string', [
                'faker' => 'randomElement(["draft", "published", "archived"])',
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
    $factoryPath = $this->tempPath.'/PostFactory.php';
    expect(file_exists($factoryPath))->toBeTrue();

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
                    'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        PHP;

    expect(file_get_contents($factoryPath))->toBe($expected);
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
