<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Exception;
use Michalsn\CodeIgniterRelations\Enums\RelationTypes;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;
use Michalsn\CodeIgniterRelations\Traits\PerParentLimit;
use ReflectionMethod;

/**
 * BelongsToMany Relation
 *
 * Represents a many-to-many relationship where models are related
 * through an intermediate pivot table.
 *
 * Example: Student belongsToMany Courses (through course_student pivot)
 * Database:
 *   - students table (id, name)
 *   - courses table (id, title)
 *   - course_student table (id, course_id, student_id)
 */
class BelongsToMany extends Relation
{
    use PerParentLimit;

    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::BelongsToMany;

    /**
     * The pivot table name
     */
    protected string $pivotTable;

    /**
     * The foreign key on the pivot table for the parent model
     */
    protected string $parentPivotKey;

    /**
     * The foreign key on the pivot table for the related model
     */
    protected string $relatedPivotKey;

    /**
     * Additional pivot table columns to retrieve
     *
     * @var list<string>
     */
    protected array $pivotColumns = [];

    /**
     * The accessor name for pivot data (default: 'pivot')
     */
    protected string $pivotAccessor = 'pivot';

    /**
     * The name of the "created at" column in the pivot table
     */
    protected ?string $pivotCreatedAt = null;

    /**
     * The name of the "updated at" column in the pivot table
     */
    protected ?string $pivotUpdatedAt = null;

    /**
     * Constructor
     *
     * @param Model       $parentModel       The parent model instance
     * @param string      $relatedModelClass The related model class name
     * @param string|null $pivotTable        Optional pivot table override
     * @param string|null $parentPivotKey    Optional parent pivot key override
     * @param string|null $relatedPivotKey   Optional related pivot key override
     * @param string|null $parentKey         Optional parent key override
     * @param string|null $relatedKey        Optional related key override
     */
    public function __construct(
        Model $parentModel,
        string $relatedModelClass,
        ?string $pivotTable = null,
        ?string $parentPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ) {
        // Initialize parent and related models
        $this->parentModel = $parentModel;
        $this->model       = model($relatedModelClass);

        // Set primary keys
        $this->primaryKey = $parentKey ?? get_model_property($parentModel, 'primaryKey');
        $this->foreignKey = $relatedKey ?? get_model_property($this->model, 'primaryKey');

        // Set pivot table and keys
        $this->pivotTable      = $pivotTable ?? $this->guessPivotTable();
        $this->parentPivotKey  = $parentPivotKey ?? $this->guessParentPivotKey();
        $this->relatedPivotKey = $relatedPivotKey ?? $this->guessRelatedPivotKey();
    }

    /**
     * Guess the pivot table name based on convention
     *
     * Combines both table names in alphabetical order, singular form
     * Example: courses + students = course_student
     *
     * @return string The guessed pivot table name
     */
    protected function guessPivotTable(): string
    {
        helper('inflector');

        $parentTable  = get_model_property($this->parentModel, 'table');
        $relatedTable = get_model_property($this->model, 'table');

        // Get singular forms
        $tables = [
            singular($parentTable),
            singular($relatedTable),
        ];

        // Sort alphabetically
        sort($tables);

        return implode('_', $tables);
    }

    /**
     * Guess the parent pivot key (foreign key for parent in pivot table)
     *
     * @return string The guessed parent pivot key
     */
    protected function guessParentPivotKey(): string
    {
        return get_foreign_key($this->parentModel);
    }

    /**
     * Guess the related pivot key (foreign key for related in pivot table)
     *
     * @return string The guessed related pivot key
     */
    protected function guessRelatedPivotKey(): string
    {
        return get_foreign_key($this->model);
    }

    /**
     * Specify additional pivot table columns to retrieve
     *
     * @param array|string ...$columns The pivot column names to retrieve (array or variadic strings)
     */
    public function withPivot(array|string ...$columns): static
    {
        // Flatten array if first argument is an array
        if (isset($columns[0]) && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        return $this;
    }

    /**
     * Customize the name of the pivot accessor
     *
     * By default, pivot data is accessible via the 'pivot' property.
     * Use this method to customize the accessor name.
     *
     * Example: $student->courses()->as('enrollment') makes pivot accessible as $course->enrollment
     *
     * @param string $accessor The custom accessor name for pivot data
     */
    public function as(string $accessor): static
    {
        $this->pivotAccessor = $accessor;

        return $this;
    }

    /**
     * Automatically include timestamp columns from the pivot table
     *
     * Stores the timestamp column names for later use in automatic timestamp management
     * and Time object conversion. Also adds these columns to the pivot data retrieval.
     *
     * @param string|null $createdAt Custom created_at column name (default: 'created_at')
     * @param string|null $updatedAt Custom updated_at column name (default: 'updated_at')
     */
    public function withTimestamps(?string $createdAt = null, ?string $updatedAt = null): static
    {
        $this->pivotCreatedAt = $createdAt ?? 'created_at';
        $this->pivotUpdatedAt = $updatedAt ?? 'updated_at';

        return $this->withPivot([$this->pivotCreatedAt, $this->pivotUpdatedAt]);
    }

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

        // Load related records through pivot table
        // Pass 'array' or 'object' to match parent format, otherwise use model's default entity
        $relatedReturnType = ($returnType === 'array' || $returnType === 'object') ? $returnType : null;
        $related           = $this->loadRelatedThroughPivot($ids, $relatedReturnType);

        // If there are nested relations, load them
        if ($this->nestedRelations !== []) {
            $related = $this->model->eagerLoadRelations($related, $this->nestedRelations, $returnType);
        }
        // Group by parent ID and convert to appropriate format
        $grouped = $this->groupByParentId($related);

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

        return $this->loadRelatedThroughPivot([$id]);
    }

    /**
     * Load related records through pivot table
     *
     * @param array       $parentIds  Parent IDs to load relations for
     * @param string|null $returnType The desired return type for related data
     *
     * @return array The related records
     */
    protected function loadRelatedThroughPivot(array $parentIds, ?string $returnType = null): array
    {
        $relatedTable = get_model_property($this->model, 'table');

        // Set return type: 'array', 'object', or use model's default entity
        if ($returnType === 'array') {
            $this->model->asArray();
        } elseif ($returnType === 'object') {
            $this->model->asObject();
        }
        // Otherwise use model's default entity class

        // Select related table columns and pivot keys (always included)
        $this->model
            ->select("{$relatedTable}.*")
            ->select("{$this->pivotTable}.{$this->parentPivotKey} as __pivot_parent_id")
            ->select("{$this->pivotTable}.{$this->relatedPivotKey} as __pivot_related_id");

        // Select additional pivot columns if specified via withPivot()
        foreach ($this->pivotColumns as $column) {
            $this->model->select("{$this->pivotTable}.{$column} as __pivot_{$column}");
        }

        $this->model
            ->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->foreignKey} = {$this->pivotTable}.{$this->relatedPivotKey}",
                'inner',
            )
            ->whereIn("{$this->pivotTable}.{$this->parentPivotKey}", $parentIds);

        // Apply custom query callback if provided
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

        if ($this->hasLimitInQuery()) {
            // Use the pivot's parent key alias for partitioning
            return $this->executeWithWindowFunction($parentIds, '__pivot_parent_id');
        }

        return $this->model->findAll();
    }

    /**
     * Attach one or more related records to the parent
     *
     * Creates pivot table entries to link existing records.
     * Accepts single ID, array of IDs, or entity/entities.
     * Optionally accepts pivot data to store additional columns.
     *
     * Examples:
     * - attach(1) - Single ID
     * - attach([1, 2, 3]) - Multiple IDs
     * - attach(1, ['expires' => '2024-01-01']) - Single ID with pivot data
     * - attach([1, 2], ['expires' => '2024-01-01']) - Multiple IDs with same pivot data
     * - attach([1 => ['expires' => '2024-01-01'], 2 => ['expires' => '2024-02-01']]) - Different pivot data per ID
     *
     * @param mixed $ids       Single ID, array of IDs, or entity/entities
     * @param array $pivotData Optional additional pivot table data
     *
     * @throws RelationException If called without parent context
     */
    public function attach(mixed $ids, array $pivotData = []): void
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('attach()');
        }

        // Check if $ids is an associative array with pivot data for each ID
        if (is_array($ids) && $this->isAssociativeArrayWithPivotData($ids)) {
            // Format: [1 => ['expires' => '2024-01-01'], 2 => ['expires' => '2024-02-01']]
            foreach ($ids as $relatedId => $itemPivotData) {
                $this->attachPivot($this->contextParentId, $relatedId, $itemPivotData);
            }
        } else {
            // Format: attach(1) or attach([1, 2, 3]) or attach(1, ['expires' => '2024-01-01'])
            $relatedIds = $this->normalizeIds($ids);

            foreach ($relatedIds as $relatedId) {
                $this->attachPivot($this->contextParentId, $relatedId, $pivotData);
            }
        }
    }

    /**
     * Detach one or more related records from the parent
     *
     * Removes pivot table entries. If no IDs provided, detaches all.
     *
     * @param mixed $ids Single ID, array of IDs, entity/entities, or null for all
     *
     * @return int Number of records detached
     *
     * @throws RelationException If called without parent context
     */
    public function detach(mixed $ids = null): int
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('detach()');
        }

        $db = db_connect();

        // Build the base query
        $builder = $db->table($this->pivotTable)
            ->where($this->parentPivotKey, $this->contextParentId);

        // If IDs provided, only detach those specific records
        if ($ids !== null) {
            $relatedIds = $this->normalizeIds($ids);
            $builder->whereIn($this->relatedPivotKey, $relatedIds);
        }

        $builder->delete();

        return $db->affectedRows();
    }

    /**
     * Sync the relationship to match exactly the given IDs
     *
     * Detaches records not in the list and attaches records that are missing.
     * Updates existing pivot records if data has changed.
     * After sync, the relationship will contain exactly the provided IDs.
     *
     * Examples:
     * - sync([1, 2, 3]) - Just IDs
     * - sync([1, 2, 3], ['active' => true]) - All IDs with same pivot data
     * - sync([1 => ['expires' => true], 2, 3]) - Mixed: some with pivot data, some without
     *
     * @param mixed $ids       Array of IDs or entities to sync
     * @param array $pivotData Optional pivot data to apply to all IDs
     *
     * @return array{attached: array, detached: array, updated: array} IDs that were attached, detached, and updated
     *
     * @throws RelationException If called without parent context
     */
    public function sync(mixed $ids, array $pivotData = []): array
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('sync()');
        }

        $db = db_connect();

        // Parse IDs and pivot data
        $records = $this->parseSyncRecords($ids, $pivotData);

        // Get currently attached IDs
        $currentIds = $db->table($this->pivotTable)
            ->where($this->parentPivotKey, $this->contextParentId)
            ->get()
            ->getResultArray();

        $currentIds = array_column($currentIds, $this->relatedPivotKey);
        $desiredIds = array_keys($records);

        // Determine what to attach, update, and detach
        $toAttach = array_diff($desiredIds, $currentIds);
        $toUpdate = array_intersect($desiredIds, $currentIds);
        $toDetach = array_diff($currentIds, $desiredIds);

        // Detach records that shouldn't be there
        if ($toDetach !== []) {
            $db->table($this->pivotTable)
                ->where($this->parentPivotKey, $this->contextParentId)
                ->whereIn($this->relatedPivotKey, $toDetach)
                ->delete();
        }

        // Update existing records
        $updated = [];

        foreach ($toUpdate as $relatedId) {
            if ($this->updateExistingPivot($this->contextParentId, $relatedId, $records[$relatedId])) {
                $updated[] = $relatedId;
            }
        }

        // Attach new records
        foreach ($toAttach as $relatedId) {
            $this->attachPivot($this->contextParentId, $relatedId, $records[$relatedId]);
        }

        return [
            'attached' => $toAttach,
            'detached' => $toDetach,
            'updated'  => $updated,
        ];
    }

    /**
     * Normalize IDs from various input formats
     *
     * Accepts: single ID, array of IDs, entity, array of entities
     *
     * @param mixed $ids The IDs to normalize
     *
     * @return array Array of integer/string IDs
     */
    protected function normalizeIds(mixed $ids): array
    {
        // Single entity
        if (is_object($ids)) {
            $primaryKey = get_model_property($this->model, 'primaryKey');

            return [$ids->{$primaryKey}];
        }

        // Array of IDs or entities
        if (is_array($ids)) {
            $normalized = [];
            $primaryKey = get_model_property($this->model, 'primaryKey');

            foreach ($ids as $item) {
                $normalized[] = is_object($item) ? $item->{$primaryKey} : $item;
            }

            return $normalized;
        }

        // Single ID
        return [$ids];
    }

    /**
     * Check if array is associative with pivot data for each ID
     *
     * Determines if the array format is: [id => [pivot data], id => [pivot data]]
     *
     * @param array $array The array to check
     *
     * @return bool True if associative array with pivot data
     */
    protected function isAssociativeArrayWithPivotData(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        // Check if array has string keys or non-sequential numeric keys
        // and that the first value is an array (pivot data)
        foreach ($array as $key => $value) {
            // If key is string or value is array, it's likely pivot data format
            if (is_string($key) || is_array($value)) {
                return is_array($value);
            }

            // If we hit a non-array value with numeric key, it's a regular ID array
            return false;
        }
    }

    /**
     * Parse sync records into normalized format
     *
     * Converts various input formats into: [id => pivot_data, id => pivot_data]
     *
     * @param mixed $ids       The IDs to sync
     * @param array $pivotData Default pivot data to apply to all IDs
     *
     * @return array Associative array of [id => pivot_data]
     */
    protected function parseSyncRecords(mixed $ids, array $pivotData): array
    {
        $records = [];

        // Check if $ids is an associative array with pivot data for each ID
        // Format: [1 => ['expires' => true], 2, 3]
        if (is_array($ids) && $this->isAssociativeArrayWithPivotData($ids)) {
            foreach ($ids as $id => $value) {
                if (is_array($value)) {
                    // ID has specific pivot data
                    $records[$id] = $value;
                } else {
                    // Value is the ID, use default pivot data
                    $records[$value] = $pivotData;
                }
            }
        } else {
            // Format: sync([1, 2, 3]) or sync([1, 2, 3], ['active' => true])
            $normalizedIds = $this->normalizeIds($ids);

            foreach ($normalizedIds as $id) {
                $records[$id] = $pivotData;
            }
        }

        return $records;
    }

    /**
     * Update existing pivot record if data has changed
     *
     * @param int|string $parentId  The parent model ID
     * @param int|string $relatedId The related model ID
     * @param array      $pivotData New pivot data
     *
     * @return bool True if record was updated, false if no changes
     */
    protected function updateExistingPivot(int|string $parentId, int|string $relatedId, array $pivotData): bool
    {
        // If no pivot data to update, skip
        if ($pivotData === []) {
            return false;
        }

        // Add timestamps if withTimestamps() was called
        if ($this->pivotUpdatedAt !== null) {
            $pivotData[$this->pivotUpdatedAt] = $this->getModelTimestamp();
        }

        $db = db_connect();

        // Update the pivot record
        $db->table($this->pivotTable)
            ->where($this->parentPivotKey, $parentId)
            ->where($this->relatedPivotKey, $relatedId)
            ->update($pivotData);

        return true;
    }

    public function save(array|object $data): false|object
    {
        if ($this->contextParentId === null) {
            throw RelationException::forMissingParentContext('save()');
        }

        $primaryKey = get_model_property($this->model, 'primaryKey');

        // Check if primary key exists - throw exception if it does
        $hasPrimaryKey = is_array($data) ? isset($data[$primaryKey]) : isset($data->{$primaryKey});

        if ($hasPrimaryKey) {
            throw RelationException::forPrimaryKeyNotAllowedInSave($primaryKey);
        }

        // Insert new record
        $insertId = $this->model->insert($data, true);

        if ($insertId === false) {
            return false;
        }

        // Attach the new record to the parent via pivot table
        $this->attachPivot($this->contextParentId, $insertId);

        // Return the created entity
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
                // Check if primary key exists - throw exception if it does
                $hasPrimaryKey = is_array($data) ? isset($data[$primaryKey]) : isset($data->{$primaryKey});

                if ($hasPrimaryKey) {
                    // Rollback and throw exception
                    $db->transRollback();

                    throw RelationException::forPrimaryKeyNotAllowedInSaveMany($primaryKey, $index);
                }

                // Insert new record
                $entityId = $this->model->insert($data, true);
                $success  = $entityId !== false;

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
                    // Attach the new record to parent
                    $this->attachPivot($this->contextParentId, $entityId);
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
        } catch (RelationException|RelationWriteException $e) {
            // Re-throw RelationWriteException and RelationException as-is
            throw $e;
        } catch (Exception $e) {
            if ($useTransaction) {
                $db->transRollback();
            }

            throw $e;
        }
    }

    /**
     * Get formatted timestamp using model's intToDate method
     *
     * Uses reflection to call the model's protected intToDate() method,
     * ensuring this package stays compatible with any CI4 date formatting changes.
     * The ReflectionMethod object is cached per model class for performance.
     *
     * @param int|null $timestamp Optional timestamp (defaults to current time)
     *
     * @return int|string The formatted timestamp
     */
    protected function getModelTimestamp(?int $timestamp = null): int|string
    {
        static $reflectionCache = [];

        $modelClass = $this->model::class;

        // Cache the ReflectionMethod object per model class
        if (! isset($reflectionCache[$modelClass])) {
            $reflectionCache[$modelClass] = new ReflectionMethod($this->model, 'intToDate');
        }

        $timestamp ??= Time::now()->getTimestamp();

        return $reflectionCache[$modelClass]->invoke($this->model, $timestamp);
    }

    /**
     * Attach a related record to the parent (create pivot entry)
     *
     * @param int|string $parentId  The parent model ID
     * @param int|string $relatedId The related model ID
     * @param array      $pivotData Additional pivot table data
     *
     * @return bool True on success
     */
    protected function attachPivot(int|string $parentId, int|string $relatedId, array $pivotData = []): bool
    {
        $db = db_connect();

        // Check if pivot entry already exists
        $existing = $db->table($this->pivotTable)
            ->where($this->parentPivotKey, $parentId)
            ->where($this->relatedPivotKey, $relatedId)
            ->get()
            ->getRow();

        if ($existing !== null) {
            // Already attached
            return true;
        }

        // Build insert data with foreign keys
        $insertData = [
            $this->parentPivotKey  => $parentId,
            $this->relatedPivotKey => $relatedId,
        ];

        // Add timestamps if withTimestamps() was called
        if ($this->pivotCreatedAt !== null || $this->pivotUpdatedAt !== null) {
            $now = $this->getModelTimestamp();

            if ($this->pivotCreatedAt !== null) {
                $insertData[$this->pivotCreatedAt] = $now;
            }

            if ($this->pivotUpdatedAt !== null) {
                $insertData[$this->pivotUpdatedAt] = $now;
            }
        }

        // Merge additional pivot data
        $insertData = array_merge($insertData, $pivotData);

        // Insert pivot entry
        return $db->table($this->pivotTable)->insert($insertData);
    }

    /**
     * Group related records by parent ID
     *
     * @param array $related The related records (with __pivot_parent_id)
     *
     * @return array Associative array keyed by parent ID
     */
    protected function groupByParentId(array $related): array
    {
        $grouped = [];

        foreach ($related as $item) {
            // Items can be arrays or objects (if nested relations were loaded)
            $isObject = is_object($item);
            $parentId = $isObject ? $item->__pivot_parent_id : $item['__pivot_parent_id'];

            // Always create pivot data with foreign keys
            $pivotData = [];

            // Add foreign keys (always included)
            if ($isObject) {
                $pivotData[$this->parentPivotKey]  = $item->__pivot_parent_id ?? null;
                $pivotData[$this->relatedPivotKey] = $item->__pivot_related_id ?? null;
            } else {
                $pivotData[$this->parentPivotKey]  = $item['__pivot_parent_id'] ?? null;
                $pivotData[$this->relatedPivotKey] = $item['__pivot_related_id'] ?? null;
            }

            // Add additional columns from withPivot()
            foreach ($this->pivotColumns as $column) {
                $pivotKey = "__pivot_{$column}";
                if ($isObject) {
                    $pivotData[$column] = $item->{$pivotKey} ?? null;
                    unset($item->{$pivotKey});
                } else {
                    $pivotData[$column] = $item[$pivotKey] ?? null;
                    unset($item[$pivotKey]);
                }
            }

            // Attach pivot data to the item using custom accessor
            if ($isObject) {
                $item->{$this->pivotAccessor} = (object) $pivotData;
                unset($item->__pivot_parent_id, $item->__pivot_related_id, $item->__rn);

                if ($item instanceof Entity) {
                    $item->syncOriginal();
                }
            } else {
                $item[$this->pivotAccessor] = $pivotData;
                unset($item['__pivot_parent_id'], $item['__pivot_related_id'], $item['__rn']);
            }

            if (! isset($grouped[$parentId])) {
                $grouped[$parentId] = [];
            }

            $grouped[$parentId][] = $item;
        }

        return $grouped;
    }
}
