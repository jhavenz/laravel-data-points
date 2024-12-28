<?php

namespace DataPoints\LaravelDataPoints\Templates;

use DataPoints\LaravelDataPoints\Contracts\DataPointTemplate;
use DataPoints\LaravelDataPoints\DataPoint;
use DataPoints\LaravelDataPoints\DTOs\DataPointCollection;
use DataPoints\LaravelDataPoints\DTOs\Field;
use DataPoints\LaravelDataPoints\DTOs\Relationship;

class BlogTemplate implements DataPointTemplate
{
    public string $name {
        get => 'blog';
    }

    public string $description {
        get => 'A blog template with posts and comments';
    }

    public string $seederClass {
        get => 'BlogDataPointSeeder';
    }

    public DataPointCollection $dataPoints {
        get => new DataPointCollection(
            $this->createPostDataPoint(),
            $this->createCommentDataPoint()
        );
    }

    private function createPostDataPoint(): DataPoint
    {
        return new DataPoint(
            name: 'Post',
            fields: collect([
                Field::from('title', 'string'),
                Field::from('slug', 'string', ['unique' => true]),
                Field::from('content', 'text'),
                Field::from('published_at', 'timestamp', ['nullable' => true]),
            ]),
            relationships: collect([
                Relationship::from('hasMany', 'Comment'),
            ])
        );
    }

    private function createCommentDataPoint(): DataPoint
    {
        return new DataPoint(
            name: 'Comment',
            fields: collect([
                Field::from('content', 'text'),
                Field::from('author_name', 'string'),
                Field::from('author_email', 'string'),
            ]),
            relationships: collect([
                Relationship::from('belongsTo', 'Post'),
            ])
        );
    }
}
