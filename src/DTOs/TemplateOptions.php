<?php

namespace DataPoints\LaravelDataPoints\DTOs;

use DataPoints\LaravelDataPoints\Enums\ControllerType;

class TemplateOptions
{
    public function __construct(
        public ControllerType $controllerType = ControllerType::NONE {
            get => $this->controllerType;
        },
        public bool $withTests = false {
            get => $this->withTests;
        },
        public bool $withSeeder = true {
            get => $this->withSeeder;
        },
        public bool $withFactory = true {
            get => $this->withFactory;
        },
        public ?string $namespace = null {
            get => $this->namespace;
        },
        public array $additionalFiles = [] {
            get => $this->additionalFiles;
        },
    ) {}

    public bool $shouldGenerateController {
        get => $this->controllerType !== ControllerType::NONE;
    }

    public bool $shouldGenerateRequests {
        get => $this->controllerType->requiresRequests();
    }

    public bool $shouldGenerateResources {
        get => $this->controllerType->requiresResources();
    }

    public bool $shouldGenerateViews {
        get => $this->controllerType->requiresViews();
    }

    public static function from(array $options): self
    {
        return new self(
            controllerType: isset($options['controllerType'])
                ? ControllerType::from($options['controllerType'])
                : ControllerType::NONE,
            withTests: $options['withTests'] ?? false,
            withSeeder: $options['withSeeder'] ?? true,
            withFactory: $options['withFactory'] ?? true,
            namespace: $options['namespace'] ?? null,
            additionalFiles: $options['additionalFiles'] ?? []
        );
    }
}
