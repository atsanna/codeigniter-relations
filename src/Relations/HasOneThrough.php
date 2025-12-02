<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;

/**
 * HasOneThrough Relation
 *
 * Represents a one-to-one relationship through an intermediate model.
 *
 * Example: Country hasOneThrough Profile through User
 * Database:
 *   countries.id -> users.country_id -> profiles.user_id
 *
 * This allows accessing a distantly related model through an intermediate one.
 */
class HasOneThrough extends ThroughRelation
{
    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::HasOneThrough;

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

        $related = $this->model->findAll();

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // We need to get the intermediate records to map back to parents
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

        // Map related records to parent via through mapping
        $mapped = [];

        foreach ($related as $item) {
            $secondKeyValue = is_object($item) ? $item->{$this->secondKey} : $item[$this->secondKey];

            if (isset($throughMap[$secondKeyValue])) {
                $parentId = $throughMap[$secondKeyValue];

                // For hasOneThrough, only keep the first occurrence
                if (! isset($mapped[$parentId])) {
                    $mapped[$parentId] = $item;
                }
            }
        }

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

        // Build the through query with joins
        $this->buildLazyThroughQuery($id);

        return $this->model->first();
    }

    public function save(array|object $data): never
    {
        throw RelationException::forThroughRelationNotWritable('HasOneThrough');
    }

    public function saveMany(array $dataSet): never
    {
        throw RelationException::forThroughRelationNotWritable('HasOneThrough');
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
