<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Traits\PerParentLimit;
use Michalsn\CodeIgniterRelations\Traits\SavesDirectMany;

/**
 * HasMany Relation
 *
 * Represents a one-to-many relationship where the parent model
 * has many related models.
 *
 * Example: User hasMany Posts
 * Database: posts.user_id references users.id
 */
class HasMany extends Relation
{
    use PerParentLimit;
    use SavesDirectMany;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::HasMany;

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

        // Apply relation constraints
        $this->applyRelation($ids, $this->foreignKey);

        // Check if query has LIMIT - use window function if so
        if ($this->hasLimitInQuery()) {
            // Use window function for per-parent limiting
            $related = $this->executeWithWindowFunction($ids, $this->foreignKey);
        } else {
            // Standard query without limit
            $related = $this->model->findAll();
        }

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }

        // Group by foreign key
        $grouped = $this->groupByForeignKey($related);

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

        $this->applyRelation([$id], $this->foreignKey);

        return $this->model->findAll();
    }

    protected function setRelationKeys(array|object &$data): void
    {
        if (is_array($data)) {
            $data[$this->foreignKey] = $this->contextParentId;
        } else {
            $data->{$this->foreignKey} = $this->contextParentId;
        }
    }

    /**
     * Group related records by foreign key
     *
     * @param array $related The related records
     *
     * @return array Associative array keyed by foreign key value
     */
    protected function groupByForeignKey(array $related): array
    {
        $grouped = [];

        foreach ($related as $item) {
            $isObject = is_object($item);
            $fkValue  = $isObject ? $item->{$this->foreignKey} : $item[$this->foreignKey];

            if (! isset($grouped[$fkValue])) {
                $grouped[$fkValue] = [];
            }

            if ($isObject) {
                unset($item->__rn);

                if ($item instanceof Entity) {
                    $item->syncOriginal();
                }
            } else {
                unset($item['__rn']);
            }

            $grouped[$fkValue][] = $item;
        }

        return $grouped;
    }
}
