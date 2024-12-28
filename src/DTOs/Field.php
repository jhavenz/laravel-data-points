<?php

namespace DataPoints\LaravelDataPoints\DTOs;

readonly class Field
{
    public function __construct(
        public string $name,
        public string $type,
        public FieldOptions $options
    ) {}

    public static function from(string $name, string $type, array $options = []): self
    {
        return new self(
            name: $name,
            type: $type,
            options: FieldOptions::from($options)
        );
    }

    public function __clone(): void
    {
        $this->options = clone $this->options;
    }
}
