<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Traits;

use Exception;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;

/**
 * Provides common save() and saveMany() implementation for relations
 * that support writing directly related records (HasMany, MorphMany).
 */
trait SavesDirectMany
{
    /**
     * Set relation-specific keys on the data
     *
     * Each relation must implement this to set their specific foreign keys.
     * For example:
     * - HasMany sets: $data->foreignKey = $this->contextParentId
     * - MorphMany sets: $data->morphType = ParentClass AND $data->morphId = $this->contextParentId
     *
     * @param array|object $data The data to modify (passed by reference)
     */
    abstract protected function setRelationKeys(array|object &$data): void;

    public function save(array|object $data): false|object
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('save()');
        }

        // Set relation-specific keys (foreign key for HasMany, morph keys for MorphMany)
        $this->setRelationKeys($data);

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
                // Set relation-specific keys (foreign key for HasMany, morph keys for MorphMany)
                $this->setRelationKeys($data);

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
}
