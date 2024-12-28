<?php

namespace Tests\Feature\Generators;

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\RelationshipOptions;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\RequestGenerator;
use Tests\TestCase;

class RequestGeneratorTest extends TestCase
{
    private RequestGenerator $generator;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->generator = new RequestGenerator();
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
    public function it_generates_basic_requests(): void
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
            namespace: 'App\\Http\\Requests',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $storeRequestPath = $this->tempPath . '/StorePostRequest.php';
        $updateRequestPath = $this->tempPath . '/UpdatePostRequest.php';
        $this->assertFileExists($storeRequestPath);
        $this->assertFileExists($updateRequestPath);
        
        $expectedStore = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class StorePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'title' => ['required', 'string', 'max:255'],
                        'content' => ['required', 'string'],
                    ];
                }
            }
            PHP;

        $expectedUpdate = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class UpdatePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'title' => ['sometimes', 'string', 'max:255'],
                        'content' => ['sometimes', 'string'],
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expectedStore, file_get_contents($storeRequestPath));
        $this->assertEquals($expectedUpdate, file_get_contents($updateRequestPath));
    }

    /** @test */
    public function it_handles_custom_validation_rules(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'title', type: 'string', options: [
                    'rules' => ['required', 'string', 'unique:posts,title', 'min:3'],
                ]),
                new Field(name: 'slug', type: 'string', options: [
                    'rules' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'unique:posts,slug'],
                ]),
                new Field(name: 'published_at', type: 'datetime', options: [
                    'rules' => ['nullable', 'date', 'after:today'],
                ]),
            ]),
            relationships: collect([]),
            hasTimestamps: true
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Http\\Requests',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $storeRequestPath = $this->tempPath . '/StorePostRequest.php';
        $updateRequestPath = $this->tempPath . '/UpdatePostRequest.php';
        $this->assertFileExists($storeRequestPath);
        $this->assertFileExists($updateRequestPath);
        
        $expectedStore = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class StorePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'title' => ['required', 'string', 'unique:posts,title', 'min:3'],
                        'slug' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'unique:posts,slug'],
                        'published_at' => ['nullable', 'date', 'after:today'],
                    ];
                }
            }
            PHP;

        $expectedUpdate = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class UpdatePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'title' => ['sometimes', 'string', 'unique:posts,title,' . $this->route('post'), 'min:3'],
                        'slug' => ['sometimes', 'string', 'regex:/^[a-z0-9-]+$/', 'unique:posts,slug,' . $this->route('post')],
                        'published_at' => ['nullable', 'date', 'after:today'],
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expectedStore, file_get_contents($storeRequestPath));
        $this->assertEquals($expectedUpdate, file_get_contents($updateRequestPath));
    }

    /** @test */
    public function it_handles_nested_validation_rules(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'metadata', type: 'json', options: [
                    'rules' => ['required', 'array'],
                    'nested_rules' => [
                        'title' => ['required', 'string'],
                        'description' => ['nullable', 'string'],
                        'tags' => ['required', 'array'],
                        'tags.*' => ['required', 'string', 'min:2'],
                    ],
                ]),
            ]),
            relationships: collect([]),
            hasTimestamps: true
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Http\\Requests',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $storeRequestPath = $this->tempPath . '/StorePostRequest.php';
        $this->assertFileExists($storeRequestPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class StorePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'metadata' => ['required', 'array'],
                        'metadata.title' => ['required', 'string'],
                        'metadata.description' => ['nullable', 'string'],
                        'metadata.tags' => ['required', 'array'],
                        'metadata.tags.*' => ['required', 'string', 'min:2'],
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($storeRequestPath));
    }

    /** @test */
    public function it_handles_conditional_validation_rules(): void
    {
        // Arrange
        $dataPoint = new DataPoint(
            name: 'Post',
            fields: collect([
                new Field(name: 'type', type: 'string', options: [
                    'rules' => ['required', 'in:draft,published'],
                ]),
                new Field(name: 'published_at', type: 'datetime', options: [
                    'rules' => ['required_if:type,published', 'nullable', 'date'],
                ]),
                new Field(name: 'draft_notes', type: 'text', options: [
                    'rules' => ['required_if:type,draft', 'nullable', 'string'],
                ]),
            ]),
            relationships: collect([]),
            hasTimestamps: true
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Http\\Requests',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $storeRequestPath = $this->tempPath . '/StorePostRequest.php';
        $this->assertFileExists($storeRequestPath);
        
        $expected = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class StorePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'type' => ['required', 'in:draft,published'],
                        'published_at' => ['required_if:type,published', 'nullable', 'date'],
                        'draft_notes' => ['required_if:type,draft', 'nullable', 'string'],
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expected, file_get_contents($storeRequestPath));
    }

    /** @test */
    public function it_handles_relationship_validation_rules(): void
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
                    related: 'Category',
                    options: new RelationshipOptions(
                        foreignKey: 'category_id',
                        localKey: 'id',
                        rules: ['required', 'exists:categories,id']
                    )
                ),
                new Relationship(
                    type: RelationType::BELONGS_TO_MANY,
                    related: 'Tag',
                    options: new RelationshipOptions(
                        table: 'post_tag',
                        rules: ['required', 'array'],
                        itemRules: ['required', 'exists:tags,id']
                    )
                ),
            ]),
            hasTimestamps: true
        );

        $collection = new DataPointCollection(collect([$dataPoint]));
        $options = new TemplateOptions(
            namespace: 'App\\Http\\Requests',
            outputPath: $this->tempPath
        );

        // Act
        $this->generator->generate($collection, $options);

        // Assert
        $storeRequestPath = $this->tempPath . '/StorePostRequest.php';
        $updateRequestPath = $this->tempPath . '/UpdatePostRequest.php';
        $this->assertFileExists($storeRequestPath);
        $this->assertFileExists($updateRequestPath);
        
        $expectedStore = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class StorePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'title' => ['required', 'string', 'max:255'],
                        'category_id' => ['required', 'exists:categories,id'],
                        'tags' => ['required', 'array'],
                        'tags.*' => ['required', 'exists:tags,id'],
                    ];
                }
            }
            PHP;

        $expectedUpdate = <<<'PHP'
            <?php

            namespace App\Http\Requests;

            use Illuminate\Foundation\Http\FormRequest;

            class UpdatePostRequest extends FormRequest
            {
                public function authorize(): bool
                {
                    return true;
                }

                public function rules(): array
                {
                    return [
                        'title' => ['sometimes', 'string', 'max:255'],
                        'category_id' => ['sometimes', 'exists:categories,id'],
                        'tags' => ['sometimes', 'array'],
                        'tags.*' => ['required', 'exists:tags,id'],
                    ];
                }
            }
            PHP;
        
        $this->assertEquals($expectedStore, file_get_contents($storeRequestPath));
        $this->assertEquals($expectedUpdate, file_get_contents($updateRequestPath));
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
