<?php

namespace DataPoints\LaravelDataPoints\DTOs;

use DataPoints\LaravelDataPoints\Enums\RelationType;

class Relationship
{
    public function __construct(
        public RelationType $type {
            get => $this->type;
        },
        public string $related {
            get => $this->related;
        },
        public RelationshipOptions $options {
            get => $this->options;
        }
    ) {}

    public bool $isPolymorphic {
        get => $this->type->isPolymorphic();
    }

    public bool $requiresPivotTable {
        get => $this->type->requiresPivotTable();
    }

    public static function from(string|RelationType $type, string $related, array $options = []): self
    {
        return new self(
            type: $type instanceof RelationType ? $type : RelationType::from($type),
            related: $related,
            options: RelationshipOptions::from($options)
        );
    }
}
