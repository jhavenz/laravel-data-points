<?php

namespace DataPoints\LaravelDataPoints\DTOs;

readonly class FieldOptions
{
    public function __construct(
        public bool $nullable = false,
        public bool $unique = false,
        public ?int $length = null,
        public ?string $default = null,
        public ?string $comment = null
    ) {}

    public static function from(array $options): self
    {
        return new self(
            nullable: $options['nullable'] ?? false,
            unique: $options['unique'] ?? false,
            length: $options['length'] ?? null,
            default: $options['default'] ?? null,
            comment: $options['comment'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'nullable' => $this->nullable,
            'unique' => $this->unique,
            'length' => $this->length,
            'default' => $this->default,
            'comment' => $this->comment,
        ], fn($value) => $value !== null && $value !== false);
    }
}
