<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Traits\OfMany;

/**
 * MorphOne Relation
 *
 * Represents a polymorphic one-to-one relationship where the related model
 * can belong to multiple different parent types.
 *
 * Example: Post morphOne Image, Video morphOne Image
 * Database:
 *   images: id, imageable_type, imageable_id, url
 *
 * The imageable_type stores the parent model class name,
 * and imageable_id stores the parent's ID.
 */
class MorphOne extends MorphRelation
{
    use OfMany;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::MorphOne;

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

        // Load related records
        if ($this->isOfMany) {
            // For "of many", use special constraint with join
            $this->applyOfManyConstraint($ids);
        } else {
            // Standard morphOne loading
            $this->model
                ->where($this->morphType, $parentType)
                ->whereIn($this->morphId, $ids);

            // Apply custom query callback if provided
            if ($this->queryCallback !== null) {
                ($this->queryCallback)($this->model);
            }
        }

        $related = $this->model->findAll();

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // Map by morph ID
        $mapped = $this->mapByMorphId($related);

        // Attach to parents
        foreach ($results as &$result) {
            $id = $this->getPrimaryKeyValue($result);
            $this->attachRelationToResult($result, $relationName, $mapped[$id] ?? null);
        }

        return $results;
    }

    public function lazyLoad(array|object $parent): array|object|null
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

        // For "of many", order and limit to 1
        if ($this->isOfMany) {
            $this->model->orderBy($this->ofManyColumn, $this->ofManyOrder->direction())->limit(1);
        }

        return $this->model->first();
    }

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
            // Update existing record by ID
            $id = is_array($data) ? $data[$primaryKey] : $data->{$primaryKey};

            if (! $this->model->update($id, $data)) {
                return false;
            }

            // Return the updated entity
            return $this->model->find($id);
        }

        // Check if a related record already exists for this parent
        $existing = $this->model
            ->where($this->morphType, $parentType)
            ->where($this->morphId, $this->contextParentId)
            ->first();

        if ($existing !== null) {
            // Update the existing record
            $existingId = is_object($existing) ? $existing->{$primaryKey} : $existing[$primaryKey];

            if (! $this->model->update($existingId, $data)) {
                return false;
            }

            // Return the updated entity
            return $this->model->find($existingId);
        }

        // Insert new record
        $insertId = $this->model->insert($data, true);

        if ($insertId === false) {
            return false;
        }

        // Return the inserted entity
        return $this->model->find($insertId);
    }

    public function saveMany(array $dataSet): never
    {
        throw RelationException::forSaveManyNotSupportedForSingular('MorphOne');
    }

    /**
     * Apply "of many" constraint to the query
     *
     * Uses a subquery approach to find records where the specified column
     * matches the aggregate value (MAX or MIN) grouped by morph ID.
     *
     * This ensures each parent gets only ONE related record - the one with
     * the highest (DESC) or lowest (ASC) value in the specified column.
     *
     * @param array $parentIds Parent IDs to filter by
     */
    protected function applyOfManyConstraint(array $parentIds): void
    {
        $tableName  = $this->model->getTable();
        $aggregate  = $this->ofManyOrder->value; // 'MAX' or 'MIN'
        $parentType = $this->parentModel::class;

        // Build subquery to get aggregate value per morph ID
        $subquery = db_connect()->table($tableName)
            ->select("{$this->morphId}")
            ->select("{$aggregate}({$this->ofManyColumn}) as __agg_value")
            ->where($this->morphType, $parentType)
            ->whereIn($this->morphId, $parentIds)
            ->groupBy($this->morphId)
            ->getCompiledSelect();

        // Add where conditions with qualified column names to avoid ambiguity
        $this->model
            ->where("{$tableName}.{$this->morphType}", $parentType)
            ->whereIn("{$tableName}.{$this->morphId}", $parentIds);

        // Apply query callback if set
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        // Join with the subquery to filter records
        $this->model->join(
            "({$subquery}) as __ofmany",
            "{$tableName}.{$this->morphId} = __ofmany.{$this->morphId}
             AND {$tableName}.{$this->ofManyColumn} = __ofmany.__agg_value",
            'inner',
        );
    }

    /**
     * Map related records by morph ID
     *
     * @param array $related The related records
     *
     * @return array Associative array keyed by morph ID value
     */
    protected function mapByMorphId(array $related): array
    {
        $mapped = [];

        foreach ($related as $item) {
            $morphIdValue = is_object($item) ? $item->{$this->morphId} : $item[$this->morphId];

            // For morphOne, we only keep the first occurrence
            if (! isset($mapped[$morphIdValue])) {
                $mapped[$morphIdValue] = $item;
            }
        }

        return $mapped;
    }
}
