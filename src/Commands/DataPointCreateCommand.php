<?php

namespace DataPoints\LaravelDataPoints\Commands;

use DataPoints\LaravelDataPoints\Commands\Forms\DataPointForm;
use DataPoints\LaravelDataPoints\Contracts\DataPointTemplate;
use DataPoints\LaravelDataPoints\Templates\BlogTemplate;
use Illuminate\Console\Command;
use Laravel\Prompts\SelectPrompt;

class DataPointCreateCommand extends Command
{
    protected $signature = 'datapoint:create {template?}';
    protected $description = 'Create a new data point from a template';

    /** @var class-string<DataPointTemplate>[] $templates */
    private array $templates = [
        'blog' => BlogTemplate::class,
    ];

    public function handle(): int
    {
        $templateClass = $this->templates[$this->promptForTemplate()];
        $template = new $templateClass();

        $form = new DataPointForm($template);
        $options = $form->prompt();

        $dataPoints = $template->getDataPoints($options);

        foreach ($template->generators as $generator) {
            $generator->generate($dataPoints, $options);
        }

        $this->info('Data point created successfully!');

        return self::SUCCESS;
    }

    private function promptForTemplate(): string
    {
        if (!$this->argument('template')) {
            $this->input->setArgument('template', new SelectPrompt(
                label: 'Which template would you like to use?',
                options: array_combine(
                    array_keys($this->templates),
                    array_keys($this->templates)
                ),
                default: 'blog'
            )->prompt());
        }

        return $this->argument('template');
    }
}
