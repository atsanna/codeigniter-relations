<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Model;

/**
 * Abstract base class for polymorphic relations (MorphOne, MorphMany).
 * Provides common functionality for handling morph type and ID fields.
 */
abstract class MorphRelation extends Relation
{
    /**
     * The morph type field name
     */
    protected string $morphType;

    /**
     * The morph ID field name
     */
    protected string $morphId;

    /**
     * Constructor
     *
     * @param Model       $parentModel       The parent model instance
     * @param string      $relatedModelClass The related model class
     * @param string      $morphName         The morph name (e.g., 'commentable')
     * @param string|null $type              Optional type field override
     * @param string|null $id                Optional id field override
     * @param string|null $localKey          Optional local key override
     */
    public function __construct(
        Model $parentModel,
        string $relatedModelClass,
        protected string $morphName,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null,
    ) {
        $this->parentModel = $parentModel;
        $this->model       = model($relatedModelClass);
        $this->morphType   = $type ?? $this->morphName . '_type';
        $this->morphId     = $id ?? $this->morphName . '_id';
        $this->primaryKey  = $localKey ?? get_model_property($parentModel, 'primaryKey');
        $this->foreignKey  = $this->morphId; // For compatibility with base class
    }
}
