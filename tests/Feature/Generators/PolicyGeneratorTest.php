<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\PolicyGenerator;
use Tests\TestCase;

class PolicyGeneratorTest extends TestCase
{
    private PolicyGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new PolicyGenerator();
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
    public function it_generates_basic_policy(): void
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
        $policyPath = $this->tempPath . '/PostPolicy.php';
        $this->assertFileExists($policyPath);
        
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
        
        $this->assertEquals($expected, file_get_contents($policyPath));
    }

    /** @test */
    public function it_generates_policy_with_custom_namespace(): void
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
            namespace: 'Domain\\Blog\\Models',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $policyPath = $this->tempPath . '/PostPolicy.php';
        $this->assertFileExists($policyPath);
        
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
        
        $this->assertEquals($expected, file_get_contents($policyPath));
    }

    /** @test */
    public function it_updates_auth_service_provider(): void
    {
        // Arrange
        $dataPoints = new DataPointCollection(collect([
            new DataPoint(
                name: 'Post',
                fields: collect([new Field(name: 'title', type: 'string')]),
                relationships: collect([]),
                hasTimestamps: true
            ),
            new DataPoint(
                name: 'Comment',
                fields: collect([new Field(name: 'body', type: 'text')]),
                relationships: collect([]),
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
        $providerPath = $this->tempPath . '/AuthServiceProvider.php';
        $this->assertFileExists($providerPath);
        
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
        
        $this->assertEquals($expected, file_get_contents($providerPath));
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
