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

    public function save(array|object $data): false|object
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('save()');
        }

        // Set the foreign key
        if (is_array($data)) {
            $data[$this->foreignKey] = $this->contextParentId;
        } else {
            $data->{$this->foreignKey} = $this->contextParentId;
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
        $useTransaction = $useTransaction && $db->transDepth === 0; // Don't nest transactions

        if ($useTransaction) {
            $db->transStart();
        }

        try {
            foreach ($dataSet as $index => $data) {
                // Set the foreign key
                if (is_array($data)) {
                    $data[$this->foreignKey] = $this->contextParentId;
                } else {
                    $data->{$this->foreignKey} = $this->contextParentId;
                }

                $hasPrimaryKey = is_array($data) ? isset($data[$primaryKey]) : isset($data->{$primaryKey});
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
