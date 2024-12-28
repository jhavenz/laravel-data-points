<?php

namespace DataPoints\LaravelDataPoints\Enums;

enum ControllerType: string
{
    case NONE = 'none';
    case API_RESOURCE = 'api-resource';
    case RESOURCE = 'resource';
    case INERTIA = 'inertia';
    case LIVEWIRE = 'livewire';
    case EMPTY = 'empty';

    public function requiresRequests(): bool
    {
        return match($this) {
            self::API_RESOURCE, self::RESOURCE, self::INERTIA => true,
            default => false
        };
    }

    public function requiresResources(): bool
    {
        return match($this) {
            self::API_RESOURCE => true,
            default => false
        };
    }

    public function requiresViews(): bool
    {
        return match($this) {
            self::RESOURCE, self::INERTIA => true,
            default => false
        };
    }
}
