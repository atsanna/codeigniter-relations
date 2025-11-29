<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Enums;

enum RelationTypes: string
{
    case HasOne         = 'hasOne';
    case HasMany        = 'hasMany';
    case BelongsTo      = 'belongsTo';
    case BelongsToMany  = 'belongsToMany';
    case HasOneThrough  = 'hasOneThrough';
    case HasManyThrough = 'hasManyThrough';
    case MorphOne       = 'morphOne';
    case MorphMany      = 'morphMany';
    case MorphTo        = 'morphTo';

    /**
     * Check if this relation returns a single result
     */
    public function isSingular(): bool
    {
        return in_array($this, [
            self::HasOne,
            self::BelongsTo,
            self::HasOneThrough,
            self::MorphOne,
            self::MorphTo,
        ], true);
    }

    /**
     * Check if this relation returns multiple results
     */
    public function isPlural(): bool
    {
        return ! $this->isSingular();
    }
}
