<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Traits\PerParentLimit;
use Michalsn\CodeIgniterRelations\Traits\SavesDirectMany;

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
class MorphMany extends MorphRelation
{
    use PerParentLimit;
    use SavesDirectMany;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::MorphMany;

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

    protected function setRelationKeys(array|object &$data): void
    {
        $parentType = $this->parentModel::class;

        if (is_array($data)) {
            $data[$this->morphType] = $parentType;
            $data[$this->morphId]   = $this->contextParentId;
        } else {
            $data->{$this->morphType} = $parentType;
            $data->{$this->morphId}   = $this->contextParentId;
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
