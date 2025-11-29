<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Traits\OfMany;

/**
 * HasOne Relation
 *
 * Represents a one-to-one relationship where the parent model
 * has one related model.
 *
 * Example: User hasOne Profile
 * Database: profiles.user_id references users.id
 */
class HasOne extends Relation
{
    use OfMany;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::HasOne;

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

        // Load related records
        if ($this->isOfMany) {
            // For "of many", use special constraint with join
            $this->applyOfManyConstraint($ids);
        } else {
            // Standard hasOne loading
            $this->applyRelation($ids, $this->foreignKey);
        }

        $related = $this->model->findAll();

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // Map by foreign key
        $mapped = $this->mapByForeignKey($related);

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

        $this->applyRelation([$id], $this->foreignKey);

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

        $primaryKey = get_model_property($this->model, 'primaryKey');

        // Set the foreign key
        if (is_array($data)) {
            $data[$this->foreignKey] = $this->contextParentId;
        } else {
            $data->{$this->foreignKey} = $this->contextParentId;
        }

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
        $this->model->where($this->foreignKey, $this->contextParentId);
        $existing = $this->model->first();

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
        throw RelationException::forSaveManyNotSupportedForSingular('HasOne');
    }

    /**
     * Map related records by foreign key
     *
     * @param array $related The related records
     *
     * @return array Associative array keyed by foreign key value
     */
    protected function mapByForeignKey(array $related): array
    {
        $mapped = [];

        foreach ($related as $item) {
            $fkValue = is_object($item) ? $item->{$this->foreignKey} : $item[$this->foreignKey];

            // For hasOne, we only keep the first occurrence
            if (! isset($mapped[$fkValue])) {
                $mapped[$fkValue] = $item;
            }
        }

        return $mapped;
    }

    /**
     * Apply "of many" constraint to the query
     *
     * Uses a subquery approach to find records where the specified column
     * matches the aggregate value (MAX or MIN) grouped by foreign key.
     *
     * This ensures each parent gets only ONE related record - the one with
     * the highest (DESC) or lowest (ASC) value in the specified column.
     *
     * @param array $parentIds Parent IDs to filter by
     */
    protected function applyOfManyConstraint(array $parentIds): void
    {
        $tableName = $this->model->getTable();
        $aggregate = $this->ofManyOrder->value; // 'MAX' or 'MIN'

        // Build subquery to get aggregate value per foreign key
        // Example: SELECT user_id, MAX(created_at) as agg_value FROM posts GROUP BY user_id
        $subquery = db_connect()->table($tableName)
            ->select("{$this->foreignKey}")
            ->select("{$aggregate}({$this->ofManyColumn}) as __agg_value")
            ->whereIn($this->foreignKey, $parentIds)
            ->groupBy($this->foreignKey)
            ->getCompiledSelect();

        // Add whereIn with qualified column name to avoid ambiguity
        $this->model->whereIn("{$tableName}.{$this->foreignKey}", $parentIds);

        // Apply query callback if set
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        // Join with the subquery to filter records
        // Only keep records where (foreign_key, column) matches (foreign_key, aggregate_value)
        $this->model->join(
            "({$subquery}) as __ofmany",
            "{$tableName}.{$this->foreignKey} = __ofmany.{$this->foreignKey}
             AND {$tableName}.{$this->ofManyColumn} = __ofmany.__agg_value",
            'inner',
        );
    }
}
