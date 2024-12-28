<?php

namespace DataPoints\LaravelDataPoints\DTOs;

readonly class RelationshipOptions
{
    public function __construct(
        public ?string $foreignKey = null,
        public ?string $localKey = null,
        public ?string $table = null,
        public bool $withTimestamps = false
    ) {}

    public static function from(array $options): self
    {
        return new self(
            foreignKey: $options['foreignKey'] ?? null,
            localKey: $options['localKey'] ?? null,
            table: $options['table'] ?? null,
            withTimestamps: $options['withTimestamps'] ?? false
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'foreignKey' => $this->foreignKey,
            'localKey' => $this->localKey,
            'table' => $this->table,
            'withTimestamps' => $this->withTimestamps,
        ], fn($value) => $value !== null && $value !== false);
    }
}
