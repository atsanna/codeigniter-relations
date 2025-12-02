<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Traits\PerParentLimit;

/**
 * HasManyThrough Relation
 *
 * Represents a one-to-many relationship through an intermediate model.
 *
 * Example: Country hasManyThrough Posts through Users
 * Database:
 *   countries.id -> users.country_id -> posts.user_id
 *
 * This allows accessing all distantly related models through an intermediate one.
 */
class HasManyThrough extends ThroughRelation
{
    use PerParentLimit;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::HasManyThrough;

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

        // Build the through query with joins
        $this->buildThroughJoinQuery($ids);

        if ($this->hasLimitInQuery()) {
            // Add partition column to SELECT for window function (with DB prefix)
            $throughTable         = get_model_property($this->throughModel, 'table');
            $prefixedThroughTable = $this->model->db->prefixTable($throughTable);
            $this->model->select("{$prefixedThroughTable}.{$this->firstKey}", false);

            // Partition by the through table's foreign key that points to parent
            $related = $this->executeWithWindowFunction($ids, "{$prefixedThroughTable}.{$this->firstKey}");
        } else {
            // Standard query without limit
            $related = $this->model->findAll();
        }

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // Group by parent ID via intermediate model
        $grouped = $this->groupByParentIdThroughIntermediate($related, $ids);

        // Attach to parents
        foreach ($results as &$result) {
            $id = $this->getPrimaryKeyValue($result);
            $this->attachRelationToResult($result, $relationName, $grouped[$id] ?? []);
        }

        return $results;
    }

    public function lazyLoad(array|object $parent): array
    {
        $id = $this->getPrimaryKeyValue($parent);

        // Build the through query with joins
        $this->buildLazyThroughQuery($id);

        return $this->model->findAll();
    }

    public function save(array|object $data): never
    {
        throw RelationException::forThroughRelationNotWritable('HasManyThrough');
    }

    public function saveMany(array $dataSet): never
    {
        throw RelationException::forThroughRelationNotWritable('HasManyThrough');
    }

    /**
     * Group related records by parent ID via intermediate model
     *
     * Fetches intermediate records and builds a mapping to associate
     * related records with their parent IDs.
     *
     * @param array $related The related records
     * @param array $ids     The parent IDs
     *
     * @return array Grouped records by parent ID
     */
    protected function groupByParentIdThroughIntermediate(array $related, array $ids): array
    {
        // Get the intermediate records to map back to parents
        $throughRecords = $this->throughModel
            ->select("{$this->secondLocalKey}, {$this->firstKey}")
            ->whereIn($this->firstKey, $ids)
            ->findAll();

        // Build mapping: secondLocalKey => parentId
        $throughMap = [];

        foreach ($throughRecords as $through) {
            $secondLocalValue = is_object($through)
                ? $through->{$this->secondLocalKey}
                : $through[$this->secondLocalKey];
            $parentIdValue = is_object($through)
                ? $through->{$this->firstKey}
                : $through[$this->firstKey];

            $throughMap[$secondLocalValue] = $parentIdValue;
        }

        // Group related records by parent ID via through mapping
        $grouped = [];

        foreach ($related as $item) {
            $isObject       = is_object($item);
            $secondKeyValue = $isObject ? $item->{$this->secondKey} : $item[$this->secondKey];

            if (isset($throughMap[$secondKeyValue])) {
                $parentId = $throughMap[$secondKeyValue];

                if (! isset($grouped[$parentId])) {
                    $grouped[$parentId] = [];
                }

                if ($isObject) {
                    unset($item->__rn);

                    if ($item instanceof Entity) {
                        $item->syncOriginal();
                    }
                } else {
                    unset($item['__rn']);
                }

                $grouped[$parentId][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Extract primary keys from results
     *
     * @param array $results The parent results
     *
     * @return array Array of unique primary key values
     */
    protected function extractPrimaryKeys(array $results): array
    {
        $ids = [];

        foreach ($results as $result) {
            $id = $this->getPrimaryKeyValue($result);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_unique($ids);
    }
}
