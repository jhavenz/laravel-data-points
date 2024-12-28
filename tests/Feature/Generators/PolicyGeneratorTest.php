<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Generators\PolicyGenerator;

beforeEach(function () {
    $this->generator = new PolicyGenerator();
    $this->tempPath = sys_get_temp_dir().'/laravel-data-points-test';

    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates basic policy', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
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
    $policyPath = $this->tempPath.'/PostPolicy.php';
    expect(file_exists($policyPath))->toBeTrue();

    $expected = <<<'PHP'
        <?php

        namespace App\Policies;

        use App\Models\User;
        use App\Models\Post;
        use Illuminate\Auth\Access\HandlesAuthorization;

        class PostPolicy
        {
            use HandlesAuthorization;

            public function viewAny(User $user): bool
            {
                return true;
            }

            public function view(User $user, Post $post): bool
            {
                return true;
            }

            public function create(User $user): bool
            {
                return true;
            }

            public function update(User $user, Post $post): bool
            {
                return true;
            }

            public function delete(User $user, Post $post): bool
            {
                return true;
            }

            public function restore(User $user, Post $post): bool
            {
                return true;
            }

            public function forceDelete(User $user, Post $post): bool
            {
                return true;
            }
        }
        PHP;

    expect(file_get_contents($policyPath))->toBe($expected);
});

test('it generates policy with custom namespace', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'Domain\\Blog\\Models',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $policyPath = $this->tempPath.'/PostPolicy.php';
    expect(file_exists($policyPath))->toBeTrue();

    $expected = <<<'PHP'
        <?php

        namespace App\Policies;

        use App\Models\User;
        use Domain\Blog\Models\Post;
        use Illuminate\Auth\Access\HandlesAuthorization;

        class PostPolicy
        {
            use HandlesAuthorization;

            public function viewAny(User $user): bool
            {
                return true;
            }

            public function view(User $user, Post $post): bool
            {
                return true;
            }

            public function create(User $user): bool
            {
                return true;
            }

            public function update(User $user, Post $post): bool
            {
                return true;
            }

            public function delete(User $user, Post $post): bool
            {
                return true;
            }

            public function restore(User $user, Post $post): bool
            {
                return true;
            }

            public function forceDelete(User $user, Post $post): bool
            {
                return true;
            }
        }
        PHP;

    expect(file_get_contents($policyPath))->toBe($expected);
});

test('it updates auth service provider', function () {
    // Arrange
    $postDataPoint = new DataPoint(
        name: 'Post',
        fields: collect([Field::from('title', 'string')]),
        hasTimestamps: true
    );

    $commentDataPoint = new DataPoint(
        name: 'Comment',
        fields: collect([Field::from('body', 'text')]),
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
    $providerPath = $this->tempPath.'/AuthServiceProvider.php';
    expect(file_exists($providerPath))->toBeTrue();

    $expected = <<<'PHP'
        <?php

        namespace App\Providers;

        use App\Models\Post;
        use App\Models\Comment;
        use App\Policies\PostPolicy;
        use App\Policies\CommentPolicy;
        use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

        class AuthServiceProvider extends ServiceProvider
        {
            protected $policies = [
                Post::class => PostPolicy::class,
                Comment::class => CommentPolicy::class,
            ];

            public function boot(): void
            {
                $this->registerPolicies();
            }
        }
        PHP;

    expect(file_get_contents($providerPath))->toBe($expected);
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
