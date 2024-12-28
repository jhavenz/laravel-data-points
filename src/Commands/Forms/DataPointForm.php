<?php

namespace DataPoints\LaravelDataPoints\Commands\Forms;

use DataPoints\LaravelDataPoints\Contracts\DataPointTemplate;
use DataPoints\LaravelDataPoints\DTOs\TemplateOptions;
use DataPoints\LaravelDataPoints\Enums\ControllerType;
use Illuminate\Validation\Rule;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;

use function array_column;
use function Laravel\Prompts\info;

class DataPointForm
{
    public function __construct(
        private readonly DataPointTemplate $template,
    ) {}

    public function prompt(): TemplateOptions
    {
        $defaults = $this->template->defaultOptions;

        $namespace = $this->promptForNamespace($defaults->namespace);

        // Show what will be generated and allow customization
        info('This template will generate the following:');

        $controllerType = $this->confirmControllerType($defaults->controllerType);
        $withTests = $this->confirmTests($defaults->withTests);
        $withFactory = $this->confirmFactory($defaults->withFactory);
        $withSeeder = $this->confirmSeeder($defaults->withSeeder);

        return new TemplateOptions(controllerType: $controllerType);
    }

    private function promptForNamespace(?string $default): ?string
    {
        return new TextPrompt(
            label: 'What namespace should we use?',
            placeholder: 'Enter namespace or leave empty for default',
            default: $default ?? 'App\\DataPoints',
            required: false,
        )->prompt();
    }

    private function confirmControllerType(ControllerType $default): ControllerType
    {
        if (!$this->shouldCustomize('Would you like to customize the controller type?')) {
            return $default;
        }

        return new SelectPrompt(
            label: 'Select controller type',
            options: array_column(ControllerType::cases(), 'value', 'value'),
            default: $default->value,
            validate: [Rule::enum(ControllerType::class)]
        )->prompt();
    }

    private function confirmTests(bool $default): bool
    {
        if (!$this->shouldCustomize('Would you like to customize test generation?')) {
            return $default;
        }

        return new ConfirmPrompt(
            label: 'Generate tests?',
            default: $default,
        )->prompt();
    }

    private function confirmFactory(bool $default): bool
    {
        if (!$this->shouldCustomize('Would you like to customize factory generation?')) {
            return $default;
        }

        return new ConfirmPrompt(
            label: 'Generate factories?',
            default: $default,
        )->prompt();
    }

    private function confirmSeeder(bool $default): bool
    {
        if (!$this->shouldCustomize('Would you like to customize seeder generation?')) {
            return $default;
        }

        return new ConfirmPrompt(
            label: 'Generate seeders?',
            default: $default,
        )->prompt();
    }

    private function shouldCustomize(string $message): bool
    {
        return new ConfirmPrompt(
            label: $message,
            default: false,
        )->prompt();
    }
}
