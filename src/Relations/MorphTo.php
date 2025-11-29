<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;

/**
 * MorphTo Relation
 *
 * Represents the inverse of a polymorphic relationship where the current model
 * can belong to multiple different parent types.
 *
 * Example: Comment morphTo commentable (can be Post or Video)
 * Database:
 *   comments: id, commentable_type, commentable_id, content
 *
 * The commentable_type stores the parent model class name,
 * and commentable_id stores the parent's ID.
 *
 * Unlike other relations, MorphTo doesn't have a fixed related model class
 * since it can morph to different types.
 */
class MorphTo extends Relation
{
    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::MorphTo;

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
     * @param Model       $parentModel The parent model instance
     * @param string      $morphName   The morph name (e.g., 'commentable')
     * @param string|null $type        Optional type field override
     * @param string|null $id          Optional id field override
     */
    public function __construct(
        Model $parentModel,
        protected string $morphName,
        ?string $type = null,
        ?string $id = null,
    ) {
        $this->parentModel = $parentModel;
        // No fixed related model for morphTo
        $this->model      = $parentModel;
        $this->morphType  = $type ?? $this->morphName . '_type';
        $this->morphId    = $id ?? $this->morphName . '_id';
        $this->primaryKey = $this->morphId;
        $this->foreignKey = $this->morphId;
    }

    public function eagerLoad(array $results, string $returnType, string $relationName): array
    {
        if ($results === []) {
            return $results;
        }

        // Group results by morph type
        $groupedByType = [];

        foreach ($results as $result) {
            $morphType = is_object($result) ? $result->{$this->morphType} : $result[$this->morphType];
            $morphId   = is_object($result) ? $result->{$this->morphId} : $result[$this->morphId];

            if ($morphType && $morphId) {
                if (! isset($groupedByType[$morphType])) {
                    $groupedByType[$morphType] = [];
                }
                $groupedByType[$morphType][] = $morphId;
            }
        }

        // Load each morph type separately
        $loaded = [];

        foreach ($groupedByType as $morphType => $ids) {
            // Instantiate the model for this type
            $relatedModel = model($morphType);

            // Validate that the model exists
            if ($relatedModel === null) {
                throw RelationException::forInvalidMorphModel($morphType);
            }

            // Apply custom query callback if provided
            if ($this->queryCallback !== null) {
                ($this->queryCallback)($relatedModel);
            }

            // Load the related records
            $related = $relatedModel->whereIn(get_model_property($relatedModel, 'primaryKey'), array_unique($ids))->findAll();

            // If there are nested relations, load them
            if ($this->nestedRelations !== []) {
                $related = $relatedModel->eagerLoadRelations($related, $this->nestedRelations, $returnType);
            }

            // Store in loaded array by type and id
            foreach ($related as $item) {
                $itemId = is_object($item)
                    ? $item->{get_model_property($relatedModel, 'primaryKey')}
                    : $item[get_model_property($relatedModel, 'primaryKey')];

                $loaded[$morphType][$itemId] = $item;
            }
        }

        // Attach to original results
        foreach ($results as &$result) {
            $morphType = is_object($result) ? $result->{$this->morphType} : $result[$this->morphType];
            $morphId   = is_object($result) ? $result->{$this->morphId} : $result[$this->morphId];

            $relatedData = null;
            if ($morphType && $morphId && isset($loaded[$morphType][$morphId])) {
                $relatedData = $loaded[$morphType][$morphId];
            }

            $this->attachRelationToResult($result, $relationName, $relatedData);
        }

        return $results;
    }

    public function lazyLoad(array|object $parent): array|object|null
    {
        $morphType = is_object($parent) ? $parent->{$this->morphType} : $parent[$this->morphType];
        $morphId   = is_object($parent) ? $parent->{$this->morphId} : $parent[$this->morphId];

        if (! $morphType || ! $morphId) {
            return null;
        }

        // Instantiate the model for this type
        $relatedModel = model($morphType);

        // Apply custom query callback if provided
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($relatedModel);
        }

        return $relatedModel->find($morphId);
    }

    public function save(array|object $data): never
    {
        throw RelationException::forMorphToNotWritable();
    }

    public function saveMany(array $dataSet): never
    {
        throw RelationException::forMorphToNotWritable();
    }

    /**
     * Associate the current model with a parent
     *
     * Sets the morph type and ID fields, writing immediately to database
     * and updating the entity in memory.
     *
     * Supports two usage patterns:
     *   $image->imageable()->associate($post);                    // Pass entity
     *   $image->imageable()->associate(PostModel::class, $postId); // Pass class + ID
     *
     * @param object|string   $parentOrClass The parent entity or model class name
     * @param int|string|null $id            The parent ID (required if first param is string)
     *
     * @return bool True on success, false on failure
     *
     * @throws RelationException If called without parent entity context
     */
    public function associate(object|string $parentOrClass, int|string|null $id = null): bool
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('associate()');
        }

        // Determine model class and parent ID based on parameters
        if (is_object($parentOrClass)) {
            // Extract model class from entity
            $modelClass = $parentOrClass->findModelClass();

            // Get primary key from the parent's model
            $parentModel = model($modelClass);
            $primaryKey  = get_model_property($parentModel, 'primaryKey');
            $parentId    = $parentOrClass->{$primaryKey} ?? null;

            if ($parentId === null) {
                throw RelationException::forParentMissingKey('primary key');
            }
        } else {
            if (in_array($id, [null, 0, '0', ''], true)) {
                throw RelationException::forIdRequiredWithModelClass();
            }

            $modelClass = $parentOrClass;
            $parentId   = $id;

            // Verify model class exists
            if (! class_exists($modelClass)) {
                throw RelationException::forInvalidMorphModel($modelClass);
            }
        }

        // Update database using the child's model (this->parentModel)
        $result = $this->parentModel->update($this->contextParentId, [
            $this->morphType => $modelClass,
            $this->morphId   => $parentId,
        ]);

        // Update entity's morph fields in memory
        if ($result && $this->parentEntity !== null) {
            $this->parentEntity->{$this->morphType} = $modelClass;
            $this->parentEntity->{$this->morphId}   = $parentId;
        }

        return $result;
    }

    /**
     * Dissociate the current model from its parent
     *
     * Sets the morph type and ID fields to null, writing immediately
     * to database and updating the entity in memory.
     *
     * Example:
     *   $image->imageable()->dissociate();
     *
     * @return bool True on success, false on failure
     *
     * @throws RelationException If called without parent entity context
     */
    public function dissociate(): bool
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('dissociate()');
        }

        // Update database
        $result = $this->parentModel->update($this->contextParentId, [
            $this->morphType => null,
            $this->morphId   => null,
        ]);

        // Update entity's morph fields in memory
        if ($result && $this->parentEntity !== null) {
            $this->parentEntity->{$this->morphType} = null;
            $this->parentEntity->{$this->morphId}   = null;
        }

        return $result;
    }
}
