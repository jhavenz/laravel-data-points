<?php

use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\RelationType;
use DataPoints\LaravelDataPoints\Generators\RequestGenerator;

beforeEach(function () {
    $this->generator = new RequestGenerator();
    $this->tempPath = sys_get_temp_dir().'/laravel-data-points-test';

    if (!is_dir($this->tempPath)) {
        mkdir($this->tempPath, 0755, true);
    }
});

afterEach(function () {
    removeDirectory($this->tempPath);
});

test('it generates basic requests', function () {
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
        namespace: 'App\\Http\\Requests',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $storeRequestPath = $this->tempPath.'/StorePostRequest.php';
    $updateRequestPath = $this->tempPath.'/UpdatePostRequest.php';
    expect(file_exists($storeRequestPath))->toBeTrue()
        ->and(file_exists($updateRequestPath))->toBeTrue();

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

    expect(file_get_contents($storeRequestPath))->toBe($expectedStore)
        ->and(file_get_contents($updateRequestPath))->toBe($expectedUpdate);
});

test('it handles custom validation rules', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string', [
                'rules' => ['required', 'string', 'unique:posts,title', 'min:3'],
            ]),
            Field::from('slug', 'string', [
                'rules' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'unique:posts,slug'],
            ]),
            Field::from('published_at', 'datetime', [
                'rules' => ['nullable', 'date', 'after:today'],
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Http\\Requests',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $storeRequestPath = $this->tempPath.'/StorePostRequest.php';
    $updateRequestPath = $this->tempPath.'/UpdatePostRequest.php';
    expect(file_exists($storeRequestPath))->toBeTrue()
        ->and(file_exists($updateRequestPath))->toBeTrue();

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

    expect(file_get_contents($storeRequestPath))->toBe($expectedStore)
        ->and(file_get_contents($updateRequestPath))->toBe($expectedUpdate);
});

test('it handles nested validation rules', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('metadata', 'json', [
                'rules' => ['required', 'array'],
                'nested_rules' => [
                    'title' => ['required', 'string'],
                    'description' => ['nullable', 'string'],
                    'tags' => ['required', 'array'],
                    'tags.*' => ['required', 'string', 'min:2'],
                ],
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Http\\Requests',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $storeRequestPath = $this->tempPath.'/StorePostRequest.php';
    expect(file_exists($storeRequestPath))->toBeTrue();

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

    expect(file_get_contents($storeRequestPath))->toBe($expected);
});

test('it handles conditional validation rules', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('type', 'string', [
                'rules' => ['required', 'in:draft,published'],
            ]),
            Field::from('published_at', 'datetime', [
                'rules' => ['required_if:type,published', 'nullable', 'date'],
            ]),
            Field::from('draft_notes', 'text', [
                'rules' => ['required_if:type,draft', 'nullable', 'string'],
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Http\\Requests',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $storeRequestPath = $this->tempPath.'/StorePostRequest.php';
    expect(file_exists($storeRequestPath))->toBeTrue();

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

    expect(file_get_contents($storeRequestPath))->toBe($expected);
});

test('it handles relationship validation rules', function () {
    // Arrange
    $dataPoint = new DataPoint(
        name: 'Post',
        fields: collect([
            Field::from('title', 'string'),
        ]),
        relationships: collect([
            Relationship::from(RelationType::BELONGS_TO, 'Category', [
                'foreignKey' => 'category_id',
                'localKey' => 'id',
                'rules' => ['required', 'exists:categories,id'],
            ]),
            Relationship::from(RelationType::BELONGS_TO_MANY, 'Tag', [
                'table' => 'post_tag',
                'rules' => ['required', 'array'],
                'itemRules' => ['required', 'exists:tags,id'],
            ]),
        ]),
        hasTimestamps: true
    );

    $collection = new DataPointCollection($dataPoint);
    $options = new TemplateOptions(
        namespace: 'App\\Http\\Requests',
        outputPath: $this->tempPath
    );

    // Act
    $this->generator->generate($collection, $options);

    // Assert
    $storeRequestPath = $this->tempPath.'/StorePostRequest.php';
    $updateRequestPath = $this->tempPath.'/UpdatePostRequest.php';
    expect(file_exists($storeRequestPath))->toBeTrue()
        ->and(file_exists($updateRequestPath))->toBeTrue();

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

    expect(file_get_contents($storeRequestPath))->toBe($expectedStore)
        ->and(file_get_contents($updateRequestPath))->toBe($expectedUpdate);
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
