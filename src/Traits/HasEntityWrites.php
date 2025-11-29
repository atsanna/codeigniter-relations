<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Traits;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Relations\Relation;
use ReflectionMethod;

/**
 * Allows entities to save and delete themselves, and call relation methods
 * for write operations, without explicitly referencing the model.
 *
 * Features:
 * - Entity save: $entity->save()
 * - Entity delete: $entity->delete()
 * - Relation method calls: $user->posts()->save([...])
 *
 * Example:
 *   $user = new User();
 *   $user->name = 'John';
 *   $user->save(); // Saves to database
 *
 *   $person->delete(); // Deletes from database
 *
 *   $user->posts()->save(['title' => 'New Post']); // Save related record
 */
trait HasEntityWrites
{
    use HasModelDiscovery;

    /**
     * Save the entity to the database
     *
     * For new records (without primary key), performs INSERT and updates the ID.
     * For existing records, performs UPDATE. Automatically updates timestamps
     * and syncs original state.
     *
     * @throws RelationException
     */
    public function save(): bool
    {
        $modelClass = $this->findModelClass();

        if ($modelClass === null) {
            throw RelationException::forModelClassNotFound(static::class);
        }

        $model = model($modelClass);
        $pk    = get_model_property($model, 'primaryKey');
        $isNew = in_array($this->attributes[$pk] ?? null, [null, 0, '0', ''], true);

        // Attempt to save
        if (! $model->save($this)) {
            return false;
        }

        // Set ID for new records
        if ($isNew) {
            $insertId = $model->getInsertID();
            if ($insertId) {
                $this->attributes[$pk] = $insertId;
            }

            // Set timestamps manually for new records
            if (get_model_property($model, 'useTimestamps')) {
                $createdField = get_model_property($model, 'createdField');
                $updatedField = get_model_property($model, 'updatedField');

                if ($createdField !== '' && ($this->attributes[$createdField] ?? null) === null) {
                    $this->attributes[$createdField] = $this->getTimestampValue($model, $createdField);
                }

                if ($updatedField !== '' && ($this->attributes[$updatedField] ?? null) === null) {
                    $this->attributes[$updatedField] = $this->getTimestampValue($model, $updatedField);
                }
            }
        } else {
            // Update timestamp for updates
            $updatedField = get_model_property($model, 'updatedField');
            if (get_model_property($model, 'useTimestamps') && $updatedField !== '') {
                $this->attributes[$updatedField] = $this->getTimestampValue($model, $updatedField);
            }
        }

        // Sync original state so hasChanged() returns false
        $this->syncOriginal();

        return true;
    }

    /**
     * Delete the entity from the database
     *
     * For soft deletes, updates the deleted_at timestamp.
     * For hard deletes, removes the record from the database.
     *
     * @throws RelationException
     */
    public function delete(): bool
    {
        $modelClass = $this->findModelClass();

        if ($modelClass === null) {
            throw RelationException::forModelClassNotFound(static::class);
        }

        $model = model($modelClass);
        $pk    = get_model_property($model, 'primaryKey');
        $id    = $this->attributes[$pk] ?? null;

        if (in_array($id, [null, 0, '0', ''], true)) {
            throw RelationException::forCannotDeleteWithoutPrimaryKey();
        }

        if (! $model->delete($id)) {
            return false;
        }

        // If using soft deletes, update the deleted_at field
        $useSoftDeletes = get_model_property($model, 'useSoftDeletes');
        $deletedField   = get_model_property($model, 'deletedField');

        if ($useSoftDeletes && ! empty($deletedField)) {
            $this->attributes[$deletedField] = $this->getTimestampValue($model, $deletedField);
        }

        return true;
    }

    /**
     * Magic method to enable relation method calls for write operations
     *
     * Allows calling relation methods directly on the entity for write operations.
     * The entity must have a primary key value set.
     *
     * Example: $user->posts()->save(['title' => 'New Post'])
     *
     * @param string $method The method name (relation name)
     * @param array  $params Method parameters
     *
     * @throws RelationException
     */
    public function __call(string $method, array $params): Relation
    {
        $className = $this->findModelClass();

        if ($className === null) {
            throw RelationException::forModelClassNotFound(static::class);
        }

        $model = model($className);

        if (! method_exists($model, $method)) {
            throw RelationException::forRelationMethodNotFound($method, $className);
        }

        // Get the relation instance
        $relation = $model->{$method}(...$params);

        if (! $relation instanceof Relation) {
            throw RelationException::forMethodNotARelation($method, $className);
        }

        // Get primary key from model or default to 'id'
        $primaryKey = method_exists($model, 'getPrimaryKey')
            ? $model->getPrimaryKey()
            : ($model->primaryKey ?? 'id');

        $parentId = $this->{$primaryKey} ?? null;

        if (in_array($parentId, [null, 0, '0', ''], true)) {
            throw RelationException::forCannotCallRelationWithoutId($method, $primaryKey);
        }

        // Set the parent context on the relation
        $relation->setRelationName($method);
        $relation->setParentId($parentId);
        $relation->setParentEntity($this);

        return $relation;
    }

    /**
     * Get timestamp value based on model casts and entity dates
     *
     * @param Model  $model The model instance
     * @param string $field The field name
     *
     * @return int|string|Time The timestamp value
     */
    protected function getTimestampValue($model, string $field): int|string|Time
    {
        $casts = get_model_property($model, 'casts');
        if (isset($casts[$field]) && in_array($casts[$field], ['datetime', 'timestamp'], true)) {
            return Time::now();
        }

        static $reflectionCache = [];

        $modelClass = $model::class;

        // Cache the ReflectionMethod object per model class
        if (! isset($reflectionCache[$modelClass])) {
            $reflectionCache[$modelClass] = new ReflectionMethod($model, 'intToDate');
        }

        $currentDate = Time::now()->getTimestamp();

        return $reflectionCache[$modelClass]->invoke($model, $currentDate);
    }
}
