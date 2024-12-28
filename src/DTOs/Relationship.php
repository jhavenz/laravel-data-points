<?php

namespace DataPoints\LaravelDataPoints\DTOs;

readonly class Relationship
{
    public function __construct(
        public string $type,
        public string $related,
        public RelationshipOptions $options
    ) {}

    public static function from(string $type, string $related, array $options = []): self
    {
        return new self(
            type: $type,
            related: $related,
            options: RelationshipOptions::from($options)
        );
    }

    public function isBelongsTo(): bool
    {
        return $this->type === 'belongsTo';
    }
}
