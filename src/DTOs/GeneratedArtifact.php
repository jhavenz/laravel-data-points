<?php

declare(strict_types=1);

namespace DataPoints\LaravelDataPoints\DTOs;

use Closure;

class GeneratedArtifact
{
    public function __construct(
        public string $path {
            get => $this->path;
        },
        public Closure|string $content {
            get => $this->content instanceof Closure
                ? ($this->content)()
                : $this->content;
        }
    ) {}

    public function __toString(): string
    {
        return $this->content;
    }
}
