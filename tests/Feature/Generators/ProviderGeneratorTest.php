<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Generators\ProviderGenerator;
use PHPUnit\Framework\TestCase;

class ProviderGeneratorTest extends TestCase
{
    public function test_it_generates_auth_service_provider()
    {
        $dataPoints = new DataPointCollection([
            new DataPoint(
                'Post',
                collect([
                    new Field('title', 'string'),
                    new Field('content', 'text'),
                ]),
                collect()
            ),
            new DataPoint(
                'Comment',
                collect([
                    new Field('body', 'text'),
                ]),
                collect()
            ),
        ]);

        $options = new TemplateOptions(
            outputPath: '/tmp/test',
            namespace: 'App\\Models'
        );

        $generator = new ProviderGenerator();
        $artifacts = $generator->generate($dataPoints, $options);

        $this->assertCount(1, $artifacts);
        $artifact = $artifacts->first();

        $this->assertEquals('/tmp/test/app/Providers/AuthServiceProvider.php', $artifact->path);

        $expected = <<<'PHP'
            <?php

            namespace App\Providers;

            use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
            use Illuminate\Support\Facades\Gate;
            use App\Models\Post;
            use App\Models\Comment;
            use App\Policies\PostPolicy;
            use App\Policies\CommentPolicy;

            class AuthServiceProvider extends AuthServiceProvider
            {
                /**
                 * The model to policy mappings for the application.
                 *
                 * @var array<class-string, class-string>
                 */
                protected array $policies = [
                    Post::class => PostPolicy::class,
                    Comment::class => CommentPolicy::class,
                ];

                /**
                 * Register any authentication / authorization services.
                 *
                 * @return void
                 */
                public function boot(): void
                {
                    $this->registerPolicies();

                    //
                }
            }
            PHP;

        $this->assertEquals($expected, $artifact->content);
    }
}
