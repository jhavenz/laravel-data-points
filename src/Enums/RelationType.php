<?php

namespace DataPoints\LaravelDataPoints\Enums;

enum RelationType: string
{
    case HAS_ONE = 'hasOne';
    case HAS_MANY = 'hasMany';
    case BELONGS_TO = 'belongsTo';
    case BELONGS_TO_MANY = 'belongsToMany';
    case MORPH_ONE = 'morphOne';
    case MORPH_MANY = 'morphMany';
    case MORPH_TO = 'morphTo';
    case MORPH_TO_MANY = 'morphToMany';
    case MORPH_BY_MANY = 'morphByMany';

    public function isPolymorphic(): bool
    {
        return match($this) {
            self::MORPH_ONE, self::MORPH_MANY, self::MORPH_TO,
            self::MORPH_TO_MANY, self::MORPH_BY_MANY => true,
            default => false
        };
    }

    public function requiresPivotTable(): bool
    {
        return match($this) {
            self::BELONGS_TO_MANY, self::MORPH_TO_MANY,
            self::MORPH_BY_MANY => true,
            default => false
        };
    }
}
