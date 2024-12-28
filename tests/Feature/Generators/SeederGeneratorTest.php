<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\SeederGenerator;

beforeEach(function () {
    $this->generator = new SeederGenerator();
    $this->tempPath = sys_get_temp_dir().'/laravel-data-points-test';

    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates basic seeder', function () {
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
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $seederPath = $this->tempPath.'/PostSeeder.php';
    expect(file_exists($seederPath))->toBeTrue();

    $expected = <<<'PHP'
        <?php

        namespace Database\Seeders;

        use App\Models\Post;
        use Illuminate\Database\Seeder;

        class PostSeeder extends Seeder
        {
            public function run(): void
            {
                Post::factory()
                    ->count(10)
                    ->create();
            }
        }
        PHP;

    expect(file_get_contents($seederPath))->toBe($expected);
});

test('it generates seeder with relationships', function () {
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
            Relationship::from(RelationType::BELONGS_TO, 'Category', [
                'foreignKey' => 'category_id',
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
    $seederPath = $this->tempPath.'/PostSeeder.php';
    expect(file_exists($seederPath))->toBeTrue();

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

    expect(file_get_contents($seederPath))->toBe($expected);
});

test('it updates database seeder', function () {
    // Arrange
    $postDataPoint = new DataPoint(
        name: 'Category',
        fields: collect([Field::from('name', 'string')]),
        hasTimestamps: true
    );

    $commentDataPoint = new DataPoint(
        name: 'Post',
        fields: collect([Field::from('title', 'string')]),
        relationships: collect([
            Relationship::from(RelationType::BELONGS_TO, 'Category', [
                'foreignKey' => 'category_id',
                'localKey' => 'id',
            ]),
        ]),
        hasTimestamps: true
    );

    $dataPoints = new DataPointCollection($postDataPoint, $commentDataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($dataPoints, $options);

    // Assert
    $seederPath = $this->tempPath.'/DatabaseSeeder.php';
    expect(file_exists($seederPath))->toBeTrue();

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

    expect(file_get_contents($seederPath))
        ->toBe($expected)
        ->and(strpos($expected, 'CategorySeeder::class'))
        ->toBeLessThan(strpos($expected, 'PostSeeder::class'));
    // Category should be seeded before Post due to dependency
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
