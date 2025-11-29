<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;

/**
 * BelongsTo Relation
 *
 * Represents an inverse one-to-one or one-to-many relationship where
 * the current model belongs to the related model.
 *
 * Example: Post belongsTo User
 * Database: posts.user_id references users.id
 */
class BelongsTo extends Relation
{
    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::BelongsTo;

    /**
     * The owner key on the related model
     */
    protected string $ownerKey;

    /**
     * Constructor
     *
     * @param Model       $parentModel       The parent model instance
     * @param string      $relatedModelClass The related model class name
     * @param string|null $foreignKey        Optional foreign key override (on parent)
     * @param string|null $ownerKey          Optional owner key override (on related)
     */
    public function __construct(
        Model $parentModel,
        string $relatedModelClass,
        ?string $foreignKey = null,
        ?string $ownerKey = null,
    ) {
        $relatedModel = model($relatedModelClass);

        // For belongsTo, the foreign key is on the parent (current) model
        // and references the owner key on the related model
        $this->parentModel = $parentModel;
        $this->model       = $relatedModel;
        $this->foreignKey  = $foreignKey ?? get_foreign_key($relatedModel);
        $this->ownerKey    = $ownerKey ?? get_model_property($relatedModel, 'primaryKey');
        $this->primaryKey  = $this->foreignKey; // We use foreign key to extract IDs from parent
    }

    public function eagerLoad(array $results, string $returnType, string $relationName): array
    {
        if ($results === []) {
            return $results;
        }

        // Extract foreign key values (owner IDs)
        $ownerIds = $this->extractForeignKeys($results);

        if ($ownerIds === []) {
            return $results;
        }

        // Load related parent records
        $this->model->whereIn($this->ownerKey, $ownerIds);

        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        $related = $this->model->findAll();

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // Map by owner key
        $mapped = $this->mapByOwnerKey($related);

        // Attach to children
        foreach ($results as &$result) {
            $fkValue = $this->getForeignKeyValue($result);
            $this->attachRelationToResult($result, $relationName, $mapped[$fkValue] ?? null);
        }

        return $results;
    }

    public function lazyLoad(array|object $parent): array|object|null
    {
        $fkValue = $this->getForeignKeyValue($parent);

        if ($fkValue === null) {
            return null;
        }

        $this->model->where($this->ownerKey, $fkValue);

        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        return $this->model->first();
    }

    /**
     * Save related parent data and associate with child
     *
     * For BelongsTo, this saves the parent record (if dirty) and associates
     * the child with the parent by setting the foreign key.
     *
     * Process:
     * 1. Save/update the parent record
     * 2. Set child's foreign key to parent's ID
     * 3. Save the child
     * 4. Return the parent entity
     *
     * @param array|object $data The parent data (with ID for update, without for insert)
     *
     * @return false|object The parent entity or false on validation failure
     *
     * @throws RelationException If missing parent context
     */
    public function save(array|object $data): false|object
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('save()');
        }

        // Determine if this is an update (has ID) or insert (no ID)
        $hasOwnerKey = is_array($data)
            ? isset($data[$this->ownerKey])
            : isset($data->{$this->ownerKey});

        if ($hasOwnerKey) {
            // Update existing parent
            $ownerId = is_array($data) ? $data[$this->ownerKey] : $data->{$this->ownerKey};

            if (! $this->model->update($ownerId, $data)) {
                return false;
            }
        } else {
            // Insert new parent
            $ownerId = $this->model->insert($data);

            if ($ownerId === false) {
                return false;
            }
        }

        // Associate child with parent by setting foreign key
        $childData = [$this->foreignKey => $ownerId];

        if (! $this->parentModel->update($this->contextParentId, $childData)) {
            return false;
        }

        // Update parent entity's foreign key in memory
        if ($this->parentEntity !== null) {
            $this->parentEntity->{$this->foreignKey} = $ownerId;
        }

        // Return the parent entity
        return $this->model->find($ownerId);
    }

    public function saveMany(array $dataSet): never
    {
        throw RelationException::forMethodNotSupported('saveMany()', 'BelongsTo');
    }

    /**
     * Associate the child entity with a parent
     *
     * Sets the foreign key on the child to point to the parent.
     * Must be called from entity context: $child->parent()->associate($parent)
     *
     * @param int|object|string $parent The parent entity or parent ID
     *
     * @return bool True on success, false on failure
     *
     * @throws RelationException If called without parent context
     */
    public function associate(int|object|string $parent): bool
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('associate()');
        }

        // Extract parent ID from entity or use directly if int/string
        $parentId = is_object($parent)
            ? ($parent->{$this->ownerKey} ?? null)
            : $parent;

        if ($parentId === null) {
            throw RelationException::forParentMissingKey('owner key');
        }

        // Update the child's foreign key in database
        $result = $this->parentModel->update($this->contextParentId, [
            $this->foreignKey => $parentId,
        ]);

        // Update parent entity's foreign key in memory
        if ($result && $this->parentEntity !== null) {
            $this->parentEntity->{$this->foreignKey} = $parentId;

            // Update the loaded relation in memory ONLY if parent is an object
            // This preserves any nested relations on the old entity when passing an integer
            if ($this->relationName !== null && is_object($parent)) {
                $this->parentEntity->{$this->relationName} = $parent;
            }

            // Sync original state since we've persisted to database
            $this->parentEntity->syncOriginal();
        }

        return $result;
    }

    /**
     * Dissociate the child entity from its parent
     *
     * Sets the foreign key on the child to null.
     * Must be called from entity context: $child->parent()->dissociate()
     *
     * @return bool True on success, false on failure
     *
     * @throws RelationException If called without parent context
     */
    public function dissociate(): bool
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('dissociate()');
        }

        // Set the child's foreign key to null in database
        $result = $this->parentModel->update($this->contextParentId, [
            $this->foreignKey => null,
        ]);

        // Update parent entity's foreign key in memory
        if ($result && $this->parentEntity !== null) {
            $this->parentEntity->{$this->foreignKey} = null;

            // Clear the loaded relation in memory
            if ($this->relationName !== null) {
                $this->parentEntity->{$this->relationName} = null;
            }

            // Sync original state since we've persisted to database
            $this->parentEntity->syncOriginal();
        }

        return $result;
    }

    /**
     * Get the related model instance
     *
     * @return Model The related model
     */
    public function getRelatedModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the owner key name
     *
     * @return string The owner key
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    /**
     * Get the foreign key name
     *
     * @return string The foreign key
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Extract foreign key values from results
     *
     * @param array $results The child results
     *
     * @return array Array of unique foreign key values
     */
    protected function extractForeignKeys(array $results): array
    {
        $ids = [];

        foreach ($results as $result) {
            $fkValue = $this->getForeignKeyValue($result);
            if ($fkValue !== null) {
                $ids[] = $fkValue;
            }
        }

        return array_unique($ids);
    }

    /**
     * Get foreign key value from result
     *
     * @param array|object $result The result record
     *
     * @return mixed The foreign key value
     */
    protected function getForeignKeyValue($result)
    {
        if (is_object($result)) {
            return $result->{$this->foreignKey} ?? null;
        }

        return $result[$this->foreignKey] ?? null;
    }

    /**
     * Map related records by owner key
     *
     * @param array $related The related parent records
     *
     * @return array Associative array keyed by owner key value
     */
    protected function mapByOwnerKey(array $related): array
    {
        $mapped = [];

        foreach ($related as $item) {
            $ownerKeyValue = is_object($item) ? $item->{$this->ownerKey} : $item[$this->ownerKey];

            $mapped[$ownerKeyValue] = $item;
        }

        return $mapped;
    }
}
