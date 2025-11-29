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
class HasOneThrough extends Relation
{
    /**
     * @var RelationTypes The relation type
     */
    public RelationTypes $type = RelationTypes::HasOneThrough;

    /**
     * The intermediate model
     */
    protected Model $throughModel;

    /**
     * The foreign key on the intermediate table
     */
    protected string $firstKey;

    /**
     * The foreign key on the final table
     */
    protected string $secondKey;

    /**
     * The local key on the parent table
     */
    protected string $localKey;

    /**
     * The local key on the intermediate table
     */
    protected string $secondLocalKey;

    /**
     * Constructor
     *
     * @param Model       $parentModel       The parent model instance
     * @param string      $relatedModelClass The final related model class
     * @param string      $throughModelClass The intermediate model class
     * @param string|null $firstKey          Foreign key on intermediate table
     * @param string|null $secondKey         Foreign key on final table
     * @param string|null $localKey          Local key on parent table
     * @param string|null $secondLocalKey    Local key on intermediate table
     */
    public function __construct(
        Model $parentModel,
        string $relatedModelClass,
        string $throughModelClass,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null,
    ) {
        $this->parentModel    = $parentModel;
        $this->model          = model($relatedModelClass);
        $this->throughModel   = model($throughModelClass);
        $this->localKey       = $localKey ?? get_model_property($parentModel, 'primaryKey');
        $this->firstKey       = $firstKey ?? get_foreign_key($parentModel);
        $this->secondLocalKey = $secondLocalKey ?? get_model_property($this->throughModel, 'primaryKey');
        $this->secondKey      = $secondKey ?? get_foreign_key($this->throughModel);
        $this->primaryKey     = $this->localKey;
        $this->foreignKey     = $this->secondKey; // For compatibility with base class
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

        // Build the through query with joins
        $throughTable = get_model_property($this->throughModel, 'table');
        $finalTable   = get_model_property($this->model, 'table');

        // Start with the final model
        $this->model
            ->select("{$finalTable}.*")
            ->join(
                $throughTable,
                "{$throughTable}.{$this->secondLocalKey} = {$finalTable}.{$this->secondKey}",
                'inner',
            )
            ->whereIn("{$throughTable}.{$this->firstKey}", $ids);

        // Apply custom query callback if provided
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

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

        $throughTable = get_model_property($this->throughModel, 'table');
        $finalTable   = get_model_property($this->model, 'table');

        // Build query with joins
        $this->model
            ->select("{$finalTable}.*")
            ->join(
                $throughTable,
                "{$throughTable}.{$this->secondLocalKey} = {$finalTable}.{$this->secondKey}",
                'inner',
            )
            ->where("{$throughTable}.{$this->firstKey}", $id);

        // Apply custom query callback if provided
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($this->model);
        }

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
