<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Model;
use Exception;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;
use Michalsn\CodeIgniterRelations\Traits\PerParentLimit;

/**
 * MorphMany Relation
 *
 * Represents a polymorphic one-to-many relationship where the related models
 * can belong to multiple different parent types.
 *
 * Example: Post morphMany Comments, Video morphMany Comments
 * Database:
 *   comments: id, commentable_type, commentable_id, content
 *
 * The commentable_type stores the parent model class name,
 * and commentable_id stores the parent's ID.
 */
class MorphMany extends Relation
{
    use PerParentLimit;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::MorphMany;

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

    /**
     * Eager load morphMany relation
     *
     * Loads all related records where the morph type matches the parent class
     * and the morph ID is in the parent IDs.
     *
     * When a LIMIT clause is present, uses window functions to apply the limit
     * per parent instead of globally.
     *
     * @param array  $results      The parent model results
     * @param string $returnType   The desired return type for related data
     * @param string $relationName The name of this relation
     *
     * @return array The results with relations attached
     */
    public function eagerLoad(array $results, string $returnType, string $relationName): array
    {
        if ($results === []) {
            return $results;
        }

        // Extract parent IDs
        $ids = $this->extractPrimaryKeys($results);

        if ($ids === []) {
            return $results;
        }

        // Get the parent model class name for the type field
        $parentType = $this->parentModel::class;

        // Apply morph constraints
        $this->model
            ->where($this->morphType, $parentType)
            ->whereIn($this->morphId, $ids);

        // Apply custom query callback if provided
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        if ($this->hasLimitInQuery()) {
            // Use window function for per-parent limiting
            $related = $this->executeWithWindowFunction($ids, $this->morphId);
        } else {
            // Standard query without limit
            $related = $this->model->findAll();
        }

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // Group by morph ID
        $grouped = $this->groupByMorphId($related);

        // Attach to parents
        foreach ($results as &$result) {
            $id = $this->getPrimaryKeyValue($result);
            $this->attachRelationToResult($result, $relationName, $grouped[$id] ?? []);
        }

        return $results;
    }

    /**
     * Lazy load morphMany relation
     *
     * Loads the relation on-demand for a single parent record.
     *
     * @param array|object $parent The parent record
     *
     * @return array The related records
     */
    public function lazyLoad(array|object $parent): array
    {
        $id = $this->getPrimaryKeyValue($parent);

        $parentType = $this->parentModel::class;

        $this->model
            ->where($this->morphType, $parentType)
            ->where($this->morphId, $id);

        // Apply custom query callback if provided
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        return $this->model->findAll();
    }

    /**
     * Save (upsert) a related record
     *
     * Updates existing record if primary key present, inserts new otherwise.
     * Uses contextParentId to set the morph ID and type.
     *
     * @param array|object $data The data to save (array or entity)
     *
     * @return false|object The saved entity or false on validation failure
     *
     * @throws RelationException If called without parent context
     */
    public function save(array|object $data): false|object
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('save()');
        }

        $parentType = $this->parentModel::class;

        // Set the morph type and ID
        if (is_array($data)) {
            $data[$this->morphType] = $parentType;
            $data[$this->morphId]   = $this->contextParentId;
        } else {
            $data->{$this->morphType} = $parentType;
            $data->{$this->morphId}   = $this->contextParentId;
        }

        $primaryKey = get_model_property($this->model, 'primaryKey');

        // Determine if this is an update or insert based on primary key presence
        $hasPrimaryKey = is_array($data) ? isset($data[$primaryKey]) : isset($data->{$primaryKey});

        if ($hasPrimaryKey) {
            // Update existing record
            $id = is_array($data) ? $data[$primaryKey] : $data->{$primaryKey};

            if (! $this->model->update($id, $data)) {
                return false;
            }

            // Return the updated entity
            return $this->model->find($id);
        }

        // Insert new record
        $insertId = $this->model->insert($data, true);

        if ($insertId === false) {
            return false;
        }

        // Return the inserted entity
        return $this->model->find($insertId);
    }

    /**
     * Save (upsert) multiple related records
     *
     * Batch save multiple morphed records with optional transaction support.
     * Updates records with primary key, inserts records without.
     *
     * @param array $dataSet        Array of data arrays/entities to save
     * @param bool  $useTransaction Whether to wrap in transaction (default: true)
     *
     * @return array Array of saved record IDs
     *
     * @throws RelationException      If called without parent context
     * @throws RelationWriteException If any records fail
     */
    public function saveMany(array $dataSet, bool $useTransaction = true): array
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('saveMany()');
        }

        $db             = $this->model->db;
        $succeededIds   = [];
        $failedIndexes  = [];
        $errors         = [];
        $primaryKey     = get_model_property($this->model, 'primaryKey');
        $parentType     = $this->parentModel::class;
        $useTransaction = $useTransaction && $db->transDepth === 0; // Don't nest transactions

        if ($useTransaction) {
            $db->transStart();
        }

        try {
            foreach ($dataSet as $index => $data) {
                // Set the morph type and ID
                if (is_array($data)) {
                    $data[$this->morphType] = $parentType;
                    $data[$this->morphId]   = $this->contextParentId;
                } else {
                    $data->{$this->morphType} = $parentType;
                    $data->{$this->morphId}   = $this->contextParentId;
                }

                $hasPrimaryKey = is_array($data) ? isset($data[$primaryKey]) : isset($data->{$primaryKey});
                $success       = false;
                $entityId      = null;

                if ($hasPrimaryKey) {
                    // Update existing
                    $entityId = is_array($data) ? $data[$primaryKey] : $data->{$primaryKey};
                    $success  = $this->model->update($entityId, $data);
                } else {
                    // Insert new
                    $entityId = $this->model->insert($data, true);
                    $success  = $entityId !== false;
                }

                if (! $success) {
                    $failedIndexes[] = $index;
                    $errors[$index]  = $this->model->errors();

                    if ($useTransaction) {
                        // Rollback and throw exception
                        $db->transRollback();

                        throw RelationWriteException::forTransactionRollback(
                            $succeededIds,
                            $failedIndexes,
                            $errors,
                            'save',
                            $index,
                        );
                    }
                } else {
                    $succeededIds[] = $entityId;
                }
            }

            if ($useTransaction) {
                $db->transComplete();
            }

            // If there were failures and no transaction, throw exception with partial results
            if ($failedIndexes !== []) {
                throw RelationWriteException::forPartialFailure(
                    $succeededIds,
                    $failedIndexes,
                    $errors,
                );
            }

            return $succeededIds;
        } catch (RelationWriteException $e) {
            throw $e;
        } catch (Exception $e) {
            if ($useTransaction) {
                $db->transRollback();
            }

            throw $e;
        }
    }

    /**
     * Group related records by morph ID
     *
     * @param array $related The related records
     *
     * @return array Associative array keyed by morph ID value
     */
    protected function groupByMorphId(array $related): array
    {
        $grouped = [];

        foreach ($related as $item) {
            $isObject     = is_object($item);
            $morphIdValue = $isObject ? $item->{$this->morphId} : $item[$this->morphId];

            if (! isset($grouped[$morphIdValue])) {
                $grouped[$morphIdValue] = [];
            }

            if ($isObject) {
                unset($item->__rn);

                if ($item instanceof Entity) {
                    $item->syncOriginal();
                }
            } else {
                unset($item['__rn']);
            }

            $grouped[$morphIdValue][] = $item;
        }

        return $grouped;
    }
}
