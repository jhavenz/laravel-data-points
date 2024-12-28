<?php

namespace DataPoints\LaravelDataPoints\DTOs;

use DataPoints\LaravelDataPoints\DataPoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

readonly class DataPointCollection
{
    use ForwardsCalls;

    /** @var Collection<int, DataPoint> */
    private Collection $items;

    public function __construct(DataPoint ...$dataPoints)
    {
        $this->items = collect($dataPoints);
    }

    public function __call(string $method, array $arguments)
    {
        return $this->forwardDecoratedCallTo($this->items, $method, $arguments);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return forward_static_call_array([DataPoint::class, $name], $arguments);
    }
}
