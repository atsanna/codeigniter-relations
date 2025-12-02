<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Relations;

use CodeIgniter\Model;

/**
 * Abstract base class for "through" relations (HasOneThrough, HasManyThrough).
 * Provides common functionality for handling relationships through an intermediate model.
 */
abstract class ThroughRelation extends Relation
{
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

    /**
     * Build the through join query for eager loading
     *
     * Sets up the join between the intermediate and final tables,
     * filters by parent IDs, and applies any custom query callbacks.
     *
     * @param array $ids Parent IDs to filter by
     */
    protected function buildThroughJoinQuery(array $ids): void
    {
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
    }

    /**
     * Build the through join query for lazy loading
     *
     * Sets up the join between the intermediate and final tables
     * for a single parent ID.
     *
     * @param int|string $id Parent ID to filter by
     */
    protected function buildLazyThroughQuery(int|string $id): void
    {
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
    }
}
